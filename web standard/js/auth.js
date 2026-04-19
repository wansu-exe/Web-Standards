/**
 * Authentication Module
 * Elixr University Classroom Management System
 * 
 * Handles login, registration, and session management
 */

// DOM Elements
const loginLink = document.getElementById('login-link');
const registerLink = document.getElementById('register-link');
const welcomeDropdown = document.getElementById('welcome-dropdown');
const welcomeName = document.getElementById('welcome-name');
const logoutBtn = document.getElementById('logoutBtn');
const protectedItems = document.querySelectorAll('.protected');
const facultyOnlyItems = document.querySelectorAll('.faculty-only');
const studentOnlyItems = document.querySelectorAll('.student-only');
const authCta = document.getElementById('auth-cta');
const quickActions = document.getElementById('quick-actions');

// Password visibility constants
const PASSWORD_MIN_LENGTH = 6;

/**
 * Update navigation display based on auth state
 */
function updateMenuDisplay() {
    const user = getStoredUser();
    const loggedIn = isLoggedIn();
    
    if (loggedIn && user) {
        // Hide login/register links
        if (loginLink) loginLink.parentElement.style.display = 'none';
        if (registerLink) registerLink.parentElement.style.display = 'none';
        
        // Show welcome dropdown
        if (welcomeDropdown) {
            welcomeDropdown.style.display = 'block';
            if (welcomeName) {
                welcomeName.textContent = `Welcome, ${user.first_name}!`;
            }
        }
        
        // Show protected items
        protectedItems.forEach(item => item.style.display = 'block');
        
        // Show/hide role-specific items
        const isFacultyUser = user.role === 'faculty';
        facultyOnlyItems.forEach(item => {
            item.style.display = isFacultyUser ? 'block' : 'none';
        });
        studentOnlyItems.forEach(item => {
            item.style.display = isFacultyUser ? 'none' : 'block';
        });
        
        // Update home page CTAs
        if (authCta) authCta.style.display = 'none';
        if (quickActions) quickActions.style.display = 'block';
    } else {
        // Show login/register links
        if (loginLink) loginLink.parentElement.style.display = 'block';
        if (registerLink) registerLink.parentElement.style.display = 'block';
        
        // Hide welcome dropdown
        if (welcomeDropdown) welcomeDropdown.style.display = 'none';
        
        // Hide protected items
        protectedItems.forEach(item => item.style.display = 'none');
        facultyOnlyItems.forEach(item => item.style.display = 'none');
        studentOnlyItems.forEach(item => item.style.display = 'none');
        
        // Update home page CTAs
        if (authCta) authCta.style.display = 'block';
        if (quickActions) quickActions.style.display = 'none';
    }
}

/**
 * Show/hide error message with animation
 * @param {string} selector 
 * @param {boolean} show 
 * @param {string} message 
 */
function showError(selector, show, message = null) {
    const el = document.querySelector(selector);
    if (!el) return;
    
    if (message) el.textContent = message;
    
    if (show) {
        el.style.display = 'block';
        if (typeof gsap !== 'undefined') {
            gsap.fromTo(el, { opacity: 0, y: -10 }, { opacity: 1, y: 0, duration: 0.3 });
        }
    } else {
        el.style.display = 'none';
    }
}

/**
 * Clear all error messages in a form
 * @param {string} formId 
 */
function clearErrors(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    form.querySelectorAll('.error-message').forEach(el => {
        el.style.display = 'none';
    });
}

/**
 * Toggle password visibility
 * @param {HTMLElement} button 
 * @param {string} inputId 
 */
function togglePassword(button, inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const icon = button.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

/**
 * Set button loading state
 * @param {string} btnId 
 * @param {boolean} loading 
 */
function setButtonLoading(btnId, loading) {
    const btn = document.getElementById(btnId);
    const spinner = document.getElementById(btnId.replace('Btn', 'Spinner'));
    
    if (btn) btn.disabled = loading;
    if (spinner) {
        spinner.classList.toggle('d-none', !loading);
    }
}

// ==================== EVENT LISTENERS ====================

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    updateMenuDisplay();
    
    // Hamburger toggle
    const toggleButton = document.querySelector('.nav-toggle');
    const navLinks = document.querySelector('.nav-links');
    if (toggleButton && navLinks) {
        toggleButton.addEventListener('click', () => {
            navLinks.classList.toggle('show');
        });
    }
});

// Role selection for registration
const roleStudent = document.getElementById('roleStudent');
const roleFaculty = document.getElementById('roleFaculty');
const facultyCodeField = document.getElementById('facultyCodeField');

if (roleStudent) {
    roleStudent.addEventListener('change', () => {
        if (facultyCodeField) facultyCodeField.style.display = 'none';
    });
}

if (roleFaculty) {
    roleFaculty.addEventListener('change', () => {
        if (facultyCodeField) facultyCodeField.style.display = 'block';
    });
}

// Password toggle buttons
document.getElementById('toggleRegPassword')?.addEventListener('click', function() {
    togglePassword(this, 'regPassword');
});

document.getElementById('toggleRegConfirmPassword')?.addEventListener('click', function() {
    togglePassword(this, 'regConfirmPassword');
});

document.getElementById('toggleLoginPassword')?.addEventListener('click', function() {
    togglePassword(this, 'loginPassword');
});

// Real-time password validation
document.getElementById('regPassword')?.addEventListener('input', function() {
    showError('#passwordError', this.value.length > 0 && this.value.length < PASSWORD_MIN_LENGTH);
    const confirmPass = document.getElementById('regConfirmPassword');
    if (confirmPass && confirmPass.value) {
        showError('#confirmPasswordError', confirmPass.value !== this.value);
    }
});

document.getElementById('regConfirmPassword')?.addEventListener('input', function() {
    const password = document.getElementById('regPassword');
    showError('#confirmPasswordError', password && this.value !== password.value);
});

// Registration
document.getElementById('registerBtn')?.addEventListener('click', async () => {
    clearErrors('registerForm');
    
    const username = document.getElementById('regUsername')?.value.trim();
    const email = document.getElementById('regEmail')?.value.trim();
    const firstName = document.getElementById('regFname')?.value.trim();
    const lastName = document.getElementById('regLname')?.value.trim();
    const password = document.getElementById('regPassword')?.value;
    const confirmPassword = document.getElementById('regConfirmPassword')?.value;
    const role = document.querySelector('input[name="regRole"]:checked')?.value || 'student';
    const facultyCode = document.getElementById('regFacultyCode')?.value.trim();
    
    // Validation
    let isValid = true;
    
    if (!username || username.length < 3) {
        showError('#usernameError', true, 'Username must be at least 3 characters');
        isValid = false;
    }
    
    if (!email || !email.includes('@')) {
        showError('#emailError', true, 'Please enter a valid email');
        isValid = false;
    }
    
    if (!password || password.length < PASSWORD_MIN_LENGTH) {
        showError('#passwordError', true);
        isValid = false;
    }
    
    if (password !== confirmPassword) {
        showError('#confirmPasswordError', true);
        isValid = false;
    }
    
    if (!isValid) return;
    
    setButtonLoading('registerBtn', true);
    
    try {
        const userData = {
            username,
            email,
            password,
            first_name: firstName,
            last_name: lastName,
            role
        };
        
        if (role === 'faculty') {
            userData.faculty_code = facultyCode;
        }
        
        const response = await registerUser(userData);
        
        if (response.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
            modal?.hide();
            
            // Reset form
            document.getElementById('registerForm')?.reset();
            
            // Update UI
            updateMenuDisplay();
            
            // Show success and redirect
            showToast('Registration successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = 'asset/page/dashboard.html';
            }, 1000);
        }
    } catch (error) {
        showError('#registerError', true, error.message);
    } finally {
        setButtonLoading('registerBtn', false);
    }
});

// Login
document.getElementById('loginBtn')?.addEventListener('click', async () => {
    clearErrors('loginForm');
    
    const username = document.getElementById('loginUsername')?.value.trim();
    const password = document.getElementById('loginPassword')?.value;
    
    if (!username || !password) {
        showError('#loginError', true, 'Please enter username and password');
        return;
    }
    
    setButtonLoading('loginBtn', true);
    
    try {
        const response = await loginUser(username, password);
        
        if (response.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
            modal?.hide();
            
            // Reset form
            document.getElementById('loginForm')?.reset();
            
            // Update UI
            updateMenuDisplay();
            
            // Show success and redirect
            showToast('Login successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = 'asset/page/dashboard.html';
            }, 1000);
        }
    } catch (error) {
        showError('#loginError', true, error.message);
    } finally {
        setButtonLoading('loginBtn', false);
    }
});

// Logout
document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
    e.preventDefault();
    await logoutUser();
});

// Enter key submission for forms
document.getElementById('loginForm')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('loginBtn')?.click();
    }
});

document.getElementById('registerForm')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('registerBtn')?.click();
    }
});
