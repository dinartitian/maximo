<?php
session_start();
require_once 'config.php';

// Basic authentication check
if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Error and success message handling
$messages = [];

try {
    // Handle media deletion
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $stmt = $pdo->prepare("SELECT filename FROM media WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $file = $stmt->fetch();

        if ($file) {
            $filepath = "assets/media/" . $file['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            $stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
            $stmt->execute([$_GET['delete']]);
            $_SESSION['success'] = "Media berhasil dihapus!";
        }

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // Handle media status toggle
    if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
        $stmt = $pdo->prepare("UPDATE media SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$_GET['toggle']]);
        $_SESSION['success'] = "Status media berhasil diubah!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // Handle file upload with schedule
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media'])) {
        $target_dir = "assets/media/";

        // Create directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES["media"]["name"], PATHINFO_EXTENSION));

        // Validasi khusus untuk portrait mode
        if ($_POST['orientation'] === 'portrait') {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            // Tambahkan pesan error spesifik untuk portrait
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Untuk mode Portrait, hanya mendukung format gambar (JPG, JPEG, PNG, GIF).");
            }
        } else {
            // Untuk landscape, tetap mendukung semua format
            $allowed_extensions = ['mp4', 'jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Format file tidak didukung.");
            }
        }

        // Generate unique filename
        $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        // Validate schedule dates
        $schedule_start = new DateTime($_POST['schedule_start']);
        $schedule_end = new DateTime($_POST['schedule_end']);

        if ($schedule_start > $schedule_end) {
            throw new Exception("Jadwal mulai harus lebih awal dari jadwal berakhir.");
        }

        // Upload file
        if (!move_uploaded_file($_FILES["media"]["tmp_name"], $target_file)) {
            throw new Exception("Gagal mengupload file. Error: " . error_get_last()['message']);
        }

        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO media (
                filename, type, title, orientation, 
                schedule_start, schedule_end, is_active,
                upload_date
            ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        $stmt->execute([
            $new_filename,
            $file_extension,
            $_POST['title'],
            $_POST['orientation'],
            $schedule_start->format('Y-m-d H:i:s'),
            $schedule_end->format('Y-m-d H:i:s')
        ]);

        $_SESSION['success'] = "File berhasil diupload dengan jadwal!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // Fetch media for both orientations
    $stmt = $pdo->prepare("
        SELECT *, 
            CASE 
                WHEN NOW() BETWEEN schedule_start AND schedule_end AND is_active = 1 
                THEN 'Aktif'
                WHEN NOW() < schedule_start THEN 'Dijadwalkan'
                WHEN NOW() > schedule_end THEN 'Berakhir'
                ELSE 'Nonaktif'
            END as status_text
        FROM media 
        WHERE orientation = ? 
        ORDER BY upload_date DESC
    ");

    $stmt->execute(['landscape']);
    $landscape_media = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt->execute(['portrait']);
    $portrait_media = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch running texts
    $stmt = $pdo->query("
        SELECT *, 
            CASE 
                WHEN NOW() BETWEEN schedule_start AND schedule_end AND is_active = 1 
                THEN 'Aktif'
                WHEN NOW() < schedule_start THEN 'Dijadwalkan'
                WHEN NOW() > schedule_end THEN 'Berakhir'
                ELSE 'Nonaktif'
            END as status_text
        FROM running_text 
        ORDER BY created_at DESC
    ");
    $running_texts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    error_log("Admin Panel Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Digital Signage</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <style>
        .media-preview {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
        }
        .status-active {
            color: #198754;
        }
        .status-inactive {
            color: #dc3545;
        }
        .status-scheduled {
            color: #ffc107;
        }
        .status-expired {
            color: #6c757d;
        }
        .header-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        .table {
            margin-bottom: 0;
        }
        .table td {
            vertical-align: middle;
        }
        .btn-group-sm > .btn {
            padding: 0.25rem 0.5rem;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        .table td .btn-group {
            display: flex;
            justify-content: center; /* Menengahkannya secara horizontal */
            align-items: center; /* Menengahkannya secara vertikal */
            width: 50%; /* Membuat tombol memenuhi lebar kolom */
        }

    </style>
</head>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const videos = document.querySelectorAll('video');
        videos.forEach(video => {
            video.play().catch(error => {
                console.log("Autoplay was prevented. Play the video manually.");
            });
        });
    });
</script>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="header-title">Digital Signage Admin Panel</h1>
                <p class="text-muted">Kelola konten digital signage Anda</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="text-muted">
                    <i class="fa fa-user"></i> 
                    <?= htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin') ?>
                </span>
                <a href="?logout=true" class="btn btn-danger">
                    <i class="fa fa-sign-out"></i> Logout
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])) : ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fa fa-check-circle"></i> 
                <?= $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])) : ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fa fa-exclamation-circle"></i> 
                <?= $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4" id="modeTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="landscape-tab" data-bs-toggle="tab" 
                        data-bs-target="#landscape" type="button" role="tab">
                    <i class="fa fa-television"></i> Landscape Mode
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="portrait-tab" data-bs-toggle="tab" 
                        data-bs-target="#portrait" type="button" role="tab">
                    <i class="fa fa-mobile"></i> Portrait Mode
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="running-text-tab" data-bs-toggle="tab" 
                        data-bs-target="#running-text" type="button" role="tab">
                    <i class="fa fa-text-width"></i> Running Text
                </button>
            </li>
        </ul>

        <div class="tab-content" id="modeTabContent">
            <?php foreach (['landscape', 'portrait'] as $orientation) : ?>
                <div class="tab-pane fade <?= $orientation === 'landscape' ? 'show active' : '' ?>" 
                     id="<?= $orientation ?>" role="tabpanel">
                    
                    <!-- Upload Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fa fa-upload"></i> Upload Media - <?= ucfirst($orientation) ?> Mode
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <input type="hidden" name="orientation" value="<?= $orientation ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">File Media</label>
                                            <input type="file" class="form-control" name="media" required
                                                   accept="<?= $orientation === 'portrait' ? '.jpg,.jpeg,.png,.gif' : '.mp4,.jpg,.jpeg,.png,.gif' ?>">
                                            <div class="form-text">
                                                <?= $orientation === 'portrait' ? 'Format yang didukung: JPG, JPEG, PNG, GIF' : 'Format yang didukung: MP4, JPG, JPEG, PNG, GIF' ?>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Judul Media</label>
                                            <input type="text" class="form-control" name="title" required
                                                   placeholder="Masukkan judul media">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Jadwal Mulai</label>
                                            <input type="datetime-local" class="form-control" 
                                                   name="schedule_start" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Jadwal Berakhir</label>
                                            <input type="datetime-local" class="form-control" 
                                                   name="schedule_end" required>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-upload"></i> Upload Media
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Media List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fa fa-list"></i> Daftar Media - <?= ucfirst($orientation) ?> Mode
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Preview</th>
                                            <th>Judul</th>
                                            <th>Tipe</th>
                                            <th>Jadwal Mulai</th>
                                            <th>Jadwal Berakhir</th>
                                            <th>Status</th>
                                            <th width="100">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (${$orientation . '_media'} as $media) : ?>
                                            <tr>
                                                <td>
                                                    <?php if (in_array($media['type'], ['jpg', 'jpeg', 'png', 'gif'])) : ?>
                                                        <img src="assets/media/<?= htmlspecialchars($media['filename']) ?>" 
                                                             class="media-preview" alt="<?= htmlspecialchars($media['title']) ?>">
                                                    <?php elseif ($media['type'] === 'mp4') : ?>
                                                        <video class="media-preview" autoplay loop>
                                                            <source src="assets/media/<?= htmlspecialchars($media['filename']) ?>" type="video/mp4">
                                                            Your browser does not support the video tag.
                                                        </video>
                                                    <?php else : ?>
                                                        <i class="fa fa-file-video-o fa-3x text-muted"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($media['title']) ?></td>
                                                <td><?= strtoupper($media['type']) ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($media['schedule_start'])) ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($media['schedule_end'])) ?></td>
                                                <td>
                                                    <span class="status-<?= strtolower($media['status_text']) ?>">
                                                        <i class="fa fa-circle"></i>
                                                        <?= $media['status_text'] ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?delete=<?= $media['id'] ?>" 
                                                           class="btn btn-danger"
                                                           onclick="return confirm('Yakin ingin menghapus media ini?')"
                                                           title="Hapus">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty(${$orientation . '_media'})) : ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-3">
                                                    <i class="fa fa-info-circle"></i> Belum ada media untuk mode <?= ucfirst($orientation) ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Running Text Tab -->
            <div class="tab-pane fade" id="running-text" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-plus"></i> Tambah Running Text
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="runtext.php" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Konten Running Text</label>
                                        <textarea class="form-control" name="content" rows="3" required
                                                  placeholder="Masukkan teks yang akan ditampilkan"></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Orientasi</label>
                                        <select class="form-select" name="orientation" required>
                                            <option value="">Pilih orientasi...</option>
                                            <option value="landscape">Landscape</option>
                                            <option value="portrait">Portrait</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="is_active">
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Jadwal Mulai</label>
                                        <input type="datetime-local" class="form-control" 
                                               name="schedule_start" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Jadwal Berakhir</label>
                                        <input type="datetime-local" class="form-control" 
                                               name="schedule_end" required>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save"></i> Simpan Running Text
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Running Text List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-list"></i> Daftar Running Text
                        </h5>
                    </div>
                    <div class