/**
 * PharmaHeaven Authentication System
 * Handles login, logout, and session check using sessionStorage
 */

document.addEventListener('DOMContentLoaded', () => {
    // Check if we are on an auth page or dashboard
    const loginForm = document.getElementById('loginForm');
    const logoutBtn = document.getElementById('logoutBtn');

    // Auth Check
    const currentPage = window.location.pathname.split('/').pop();
    const isLoggedIn = sessionStorage.getItem('ph_session');

    if (currentPage === 'dashboard.html' && !isLoggedIn) {
        window.location.href = 'index.html';
    }

    if (currentPage === 'index.html' && isLoggedIn) {
        window.location.href = 'dashboard.html';
    }

    // Login Logic
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorMsg = document.getElementById('errorMessage');
            const btn = loginForm.querySelector('button');
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
            btn.disabled = true;

            try {
                const response = await fetch('login_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();

                if (data.success) {
                    sessionStorage.setItem('ph_session', 'true');
                    sessionStorage.setItem('ph_user', data.user.email);
                    sessionStorage.setItem('ph_user_id', data.user.id);
                    localStorage.setItem('ph_admin_name', data.user.name);
                    
                    window.location.href = 'dashboard.html';
                } else {
                    btn.innerHTML = 'Sign In <i class="fas fa-arrow-right"></i>';
                    btn.disabled = false;
                    errorMsg.innerText = data.message || 'Invalid email or password.';
                    errorMsg.style.display = 'block';
                    
                    const card = document.querySelector('.auth-card');
                    card.style.animation = 'none';
                    void card.offsetWidth;
                    card.style.animation = 'shake 0.5s';
                }
            } catch (error) {
                btn.innerHTML = 'Sign In <i class="fas fa-arrow-right"></i>';
                btn.disabled = false;
                errorMsg.innerText = 'Server error. Please try again later.';
                errorMsg.style.display = 'block';
            }
        });
    }

    // Logout Logic
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            if (confirm('Are you sure you want to logout from PharmaHeaven?')) {
                sessionStorage.clear();
                window.location.href = 'index.html';
            }
        });
    }
});

// Add shake animation to CSS dynamically if not present
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
        20%, 40%, 60%, 80% { transform: translateX(10px); }
    }
`;
document.head.appendChild(style);
