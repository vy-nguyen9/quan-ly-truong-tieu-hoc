document.addEventListener("DOMContentLoaded", function () {
    // 1. Đồng hồ & Ngày tháng
    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('vi-VN', { hour12: false });
        
        const days = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
        const dateStr = `${days[now.getDay()]}, ${now.getDate()} tháng ${now.getMonth() + 1}, ${now.getFullYear()}`;
        
        const clockEl = document.getElementById('clock');
        const dateEl = document.getElementById('currentDateFull');
        if(clockEl) clockEl.innerText = timeStr;
        if(dateEl) dateEl.innerText = dateStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // 2. Calendar Widget (Vẽ Lịch)
    const calendarDays = document.getElementById('calendarDays');
    const monthYear = document.getElementById('monthYear');
    const prevMonth = document.getElementById('prevMonth');
    const nextMonth = document.getElementById('nextMonth');

    let date = new Date();
    let currentMonth = date.getMonth();
    let currentYear = date.getFullYear();

    function renderCalendar(month, year) {
        if(!calendarDays) return;
        
        calendarDays.innerHTML = '';
        const months = ["Tháng 1", "Tháng 2", "Tháng 3", "Tháng 4", "Tháng 5", "Tháng 6", "Tháng 7", "Tháng 8", "Tháng 9", "Tháng 10", "Tháng 11", "Tháng 12"];
        monthYear.innerText = `${months[month]} - ${year}`;

        // Lấy ngày đầu tiên của tháng và số ngày trong tháng
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        // Chuyển Chủ nhật (0) thành vị trí cuối cùng trong mảng thứ 2->CN
        const startDayIndex = firstDay === 0 ? 6 : firstDay - 1; 

        // Các ngày của tháng trước (in mờ)
        for (let i = startDayIndex; i > 0; i--) {
            const div = document.createElement('div');
            div.className = 'cal-date muted';
            div.innerText = daysInPrevMonth - i + 1;
            calendarDays.appendChild(div);
        }

        // Các ngày của tháng hiện tại
        const today = new Date();
        for (let i = 1; i <= daysInMonth; i++) {
            const div = document.createElement('div');
            div.className = 'cal-date';
            
            // Highlight ngày hiện tại
            if (i === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                div.classList.add('active');
            }
            
            div.innerText = i;
            calendarDays.appendChild(div);
        }

        // Điền nốt các ô trống của tháng sau (Cho đủ khung Grid 7x6 = 42 ô)
        const totalCells = startDayIndex + daysInMonth;
        const nextMonthDays = 42 - totalCells;
        for (let i = 1; i <= nextMonthDays; i++) {
            const div = document.createElement('div');
            div.className = 'cal-date muted';
            div.innerText = i;
            calendarDays.appendChild(div);
        }
    }

    if (prevMonth && nextMonth) {
        prevMonth.addEventListener('click', () => {
            currentMonth--;
            if (currentMonth < 0) { currentMonth = 11; currentYear--; }
            renderCalendar(currentMonth, currentYear);
        });

        nextMonth.addEventListener('click', () => {
            currentMonth++;
            if (currentMonth > 11) { currentMonth = 0; currentYear++; }
            renderCalendar(currentMonth, currentYear);
        });

        // Chạy lần đầu
        renderCalendar(currentMonth, currentYear);
    }
});