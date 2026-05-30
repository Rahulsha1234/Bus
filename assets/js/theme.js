// SwiftBus Theme Toggle Manager
document.addEventListener('DOMContentLoaded', function () {
    // Inject floating theme toggle button
    const toggleBtn = document.createElement('button');
    toggleBtn.id = 'theme-toggle-btn';
    toggleBtn.className = 'theme-toggle-btn';
    toggleBtn.setAttribute('aria-label', 'Toggle theme');
    toggleBtn.innerHTML = '<i class="fa-solid fa-moon"></i>';
    document.body.appendChild(toggleBtn);

    // Update icon helper
    const updateIcon = (theme) => {
        if (theme === 'dark') {
            toggleBtn.innerHTML = '<i class="fa-solid fa-sun"></i>';
        } else {
            toggleBtn.innerHTML = '<i class="fa-solid fa-moon"></i>';
        }
    };

    // Initialize state
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    updateIcon(currentTheme);

    // Toggle click handler
    toggleBtn.addEventListener('click', function () {
        const theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        updateIcon(theme);
    });
});
