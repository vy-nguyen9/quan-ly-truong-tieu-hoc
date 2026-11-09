document.addEventListener("DOMContentLoaded", function() {
    const yearSelector = document.getElementById('yearSelector');
    
    if (yearSelector) {
        yearSelector.addEventListener('change', function() {
            const selectedYear = this.value;
            // Tải lại trang và gắn tham số year lên URL
            window.location.href = `results.php?year=${selectedYear}`;
        });
    }
});