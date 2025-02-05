<?php

// test.php
$jsonContent = file_get_contents('api.json');
$data = json_decode($jsonContent, true);
var_dump($data);
