function toggleFormFields() {
    const selectedType = document.getElementById('op_type').value.toLowerCase();

    document.querySelectorAll('.type-field').forEach(el => {
        el.style.display = 'none';
    });

    if (!selectedType) return;

    const activeFields = document.querySelectorAll(`.field-${selectedType}`);
    activeFields.forEach(el => {
        el.style.display = 'flex';
    });
}

document.addEventListener('DOMContentLoaded', toggleFormFields);
