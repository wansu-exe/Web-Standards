// ---------- Initialize Database ----------
let db = localStorage.getItem('db') ? JSON.parse(localStorage.getItem('db')) : { users: [] };
let loggedInUser = localStorage.getItem('loggedInUser') || null;
let inputInterval;

// ---------- DOM Elements ----------
const loginLink = document.getElementById('login-link');
const registerLink = document.getElementById('register-link');
const welcomeDropdown = document.getElementById('welcome-dropdown');
const welcomeName = document.getElementById('welcome-name');
const logoutBtn = document.getElementById('logoutBtn');
const protectedItems = document.querySelectorAll('.protected');

const usernameInput = document.querySelector('#regUsername');
const emailInput = document.querySelector('#regEmail');
const passwordInput = document.querySelector('#regPassword');
const confirmPasswordInput = document.querySelector('#regConfirmPassword');

// ---------- Update Navbar ----------
function updateMenuDisplay() {
    if (loggedInUser) {
        loginLink.style.display = 'none';
        registerLink.style.display = 'none';
        welcomeDropdown.style.display = 'block';
        welcomeName.textContent = `Welcome, ${loggedInUser}`;
        protectedItems.forEach(item => item.style.display = 'block');
    } else {
        loginLink.style.display = 'block';
        registerLink.style.display = 'block';
        welcomeDropdown.style.display = 'none';
        protectedItems.forEach(item => item.style.display = 'none');
    }
}

updateMenuDisplay();

// ---------- Show / Hide Error ----------
function showError(selector, show) {
    const el = document.querySelector(selector);
    if (!el) return;
    if (show) {
        gsap.to(el, { opacity: 1, height: "auto", duration: 0.3, display: "block" });
    } else {
        gsap.to(el, { opacity: 0, height: 0, duration: 0.3, onComplete: () => el.style.display = "none" });
    }
}

// ---------- Real-time Input Validation ----------
usernameInput?.addEventListener('input', () => {
    clearTimeout(inputInterval);
    inputInterval = setTimeout(() => {
        showError('#usernameError', db.users.some(u => u.username === usernameInput.value.trim()));
    }, 500);
});

emailInput?.addEventListener('input', () => {
    clearTimeout(inputInterval);
    inputInterval = setTimeout(() => {
        showError('#emailError', db.users.some(u => u.email === emailInput.value.trim()));
    }, 500);
});

passwordInput?.addEventListener('input', () => {
    showError('#passwordError', passwordInput.value.length < 6);
    showError('#confirmPasswordError', confirmPasswordInput.value !== passwordInput.value);
});

confirmPasswordInput?.addEventListener('input', () => {
    showError('#confirmPasswordError', confirmPasswordInput.value !== passwordInput.value);
});

// ---------- Register User ----------
document.querySelector('#registerBtn')?.addEventListener('click', () => {
    const username = usernameInput.value.trim();
    const email = emailInput.value.trim();
    const fname = document.querySelector('#regFname')?.value.trim() || '';
    const lname = document.querySelector('#regLname')?.value.trim() || '';
    const password = passwordInput.value.trim();
    const confirmPassword = confirmPasswordInput.value.trim();

    let isValid = true;

    if (db.users.some(u => u.username === username)) { showError('#usernameError', true); isValid = false; }
    if (db.users.some(u => u.email === email)) { showError('#emailError', true); isValid = false; }
    if (password.length < 6) { showError('#passwordError', true); isValid = false; }
    if (password !== confirmPassword) { showError('#confirmPasswordError', true); isValid = false; }

    if (!isValid) return;

    db.users.push({ username, email, fname, lname, password });
    localStorage.setItem('db', JSON.stringify(db));

    // Close modal and reset form
    const registerModal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
    registerModal?.hide();
    document.querySelector('#registerForm')?.reset();

    loggedInUser = username;
    localStorage.setItem('loggedInUser', loggedInUser);
    updateMenuDisplay();

    // Success animation
    const successMsg = document.querySelector('#registerSuccess');
    if (successMsg) {
        gsap.fromTo(successMsg, { opacity: 0 }, { opacity: 1, duration: 0.5, display: "block" });
        setTimeout(() => gsap.to(successMsg, { opacity: 0, duration: 0.5, onComplete: () => successMsg.style.display = "none" }), 4000);
    }
});

// ---------- Login User ----------
document.querySelector('#loginBtn')?.addEventListener('click', () => {
    const username = document.querySelector('#loginUsername')?.value.trim();
    const password = document.querySelector('#loginPassword')?.value.trim();
    const user = db.users.find(u => u.username === username && u.password === password);
    const loginError = document.querySelector('#loginError');

    if (user) {
        loggedInUser = user.username;
        localStorage.setItem('loggedInUser', loggedInUser);
        updateMenuDisplay();
        loginError && (loginError.style.display = 'none');

        const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
        loginModal?.hide();
    } else {
        loginError && (loginError.style.display = 'block');
    }
});

// ---------- Logout ----------
logoutBtn?.addEventListener('click', () => {
    localStorage.removeItem('loggedInUser');
    loggedInUser = null;
    updateMenuDisplay();
});

// ---------- Close Modals Clicking Outside ----------
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', e => {
        if (e.target === modal) bootstrap.Modal.getInstance(modal)?.hide();
    });
});