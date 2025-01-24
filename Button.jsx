import PropTypes from 'prop-types';

function Button({ label, onClick, className }) {
    return (
        <button 
            className={`${className} px-4 py-2 rounded`} 
            onClick={onClick}
        >
            {label}
        </button>
    );
}

Button.propTypes = {
    label: PropTypes.string.isRequired,  // label harus berupa string dan wajib
    onClick: PropTypes.func.isRequired, // onClick harus berupa fungsi dan wajib
    className: PropTypes.string,        // className bersifat opsional
};

Button.defaultProps = {
    className: '', // Default className adalah string kosong
};

export default Button;
