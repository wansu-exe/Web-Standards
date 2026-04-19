/**
 * Dashboard Module
 * Elixr University Classroom Management System
 */

// DOM Elements
const welcomeDropdown = document.getElementById('welcome-dropdown');
const welcomeName = document.getElementById('welcome-name');
const logoutBtn = document.getElementById('logoutBtn');
const facultyOnlyItems = document.querySelectorAll('.faculty-only');
const studentOnlyItems = document.querySelectorAll('.student-only');
const dashboardTitle = document.getElementById('dashboard-title');
const userGreeting = document.getElementById('user-greeting');
const classesGrid = document.getElementById('classes-grid');
const pendingActivitiesList = document.getElementById('pending-activities-list');

/**
 * Check authentication and redirect if not logged in
 */
function checkAuth() {
    if (!isLoggedIn()) {
        window.location.href = '../../student.html';
        return false;
    }
    return true;
}

/**
 * Update UI based on user role
 */
function updateRoleBasedUI() {
    const user = getStoredUser();
    if (!user) return;
    
    const isFacultyUser = user.role === 'faculty';
    
    // Update welcome message
    if (welcomeName) {
        welcomeName.textContent = `Welcome, ${user.first_name}!`;
    }
    
    if (userGreeting) {
        userGreeting.textContent = `Hello, ${user.first_name}! ${isFacultyUser ? 'Manage your classes below.' : 'View your enrolled classes below.'}`;
    }
    
    if (dashboardTitle) {
        dashboardTitle.textContent = isFacultyUser ? 'Faculty Dashboard' : 'Student Dashboard';
    }
    
    // Show/hide role-specific elements
    facultyOnlyItems.forEach(item => {
        item.style.display = isFacultyUser ? 'block' : 'none';
    });
    
    studentOnlyItems.forEach(item => {
        item.style.display = isFacultyUser ? 'none' : 'block';
    });
    
    // Show dropdown
    if (welcomeDropdown) {
        welcomeDropdown.style.display = 'block';
    }
}

/**
 * Load and render classes
 */
async function loadClasses() {
    try {
        const response = await getClasses();
        
        if (response.success) {
            renderClasses(response.data.classes);
        }
    } catch (error) {
        console.error('Error loading classes:', error);
        if (classesGrid) {
            classesGrid.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to load classes. Please try again later.
                    </div>
                </div>
            `;
        }
    }
}

/**
 * Render classes grid
 * @param {Array} classes 
 */
function renderClasses(classes) {
    if (!classesGrid) return;
    
    if (!classes || classes.length === 0) {
        const user = getStoredUser();
        const isFacultyUser = user && user.role === 'faculty';
        
        classesGrid.innerHTML = `
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-chalkboard"></i>
                    <h4>No Classes Yet</h4>
                    <p>${isFacultyUser ? 'Create your first class to get started.' : 'Join a class using a code from your instructor.'}</p>
                </div>
            </div>
        `;
        return;
    }
    
    const user = getStoredUser();
    const isFacultyUser = user && user.role === 'faculty';
    
    classesGrid.innerHTML = classes.map(cls => `
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card class-card h-100">
                <div class="card-header">
                    <span class="course-code">${escapeHtml(cls.course_code)}</span>
                    <h5 class="subject-name mb-0">${escapeHtml(cls.subject_name)}</h5>
                </div>
                <div class="card-body d-flex flex-column">
                    <p class="section mb-2">
                        <i class="fas fa-users me-2"></i>${escapeHtml(cls.section)}
                    </p>
                    ${isFacultyUser ? `
                        <div class="mb-3">
                            <small class="text-muted">Join Code:</small>
                            <div class="join-code">${cls.join_code}</div>
                        </div>
                        <p class="student-count mb-3">
                            <i class="fas fa-user-graduate me-2"></i>${cls.student_count || 0} students enrolled
                        </p>
                    ` : `
                        <p class="faculty-name mb-3">
                            <i class="fas fa-chalkboard-teacher me-2"></i>${escapeHtml(cls.faculty_name || 'Instructor')}
                        </p>
                    `}
                    <div class="mt-auto">
                        <a href="class-detail.html?id=${cls.id}" class="btn btn-outline-warning w-100">
                            <i class="fas fa-arrow-right me-2"></i>View Class
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

/**
 * Load pending activities for students
 */
async function loadPendingActivities() {
    const user = getStoredUser();
    if (!user || user.role !== 'student' || !pendingActivitiesList) return;
    
    try {
        const response = await getPendingActivities(10);
        
        if (response.success) {
            renderPendingActivities(response.data.pending);
        }
    } catch (error) {
        console.error('Error loading pending activities:', error);
        pendingActivitiesList.innerHTML = `
            <p class="text-danger text-center">Failed to load activities</p>
        `;
    }
}

/**
 * Render pending activities sidebar
 * @param {Array} activities 
 */
function renderPendingActivities(activities) {
    if (!pendingActivitiesList) return;
    
    if (!activities || activities.length === 0) {
        pendingActivitiesList.innerHTML = `
            <div class="text-center py-3">
                <i class="fas fa-check-circle text-success" style="font-size: 32px;"></i>
                <p class="mt-2 mb-0">All caught up!</p>
                <small class="text-muted">No pending activities</small>
            </div>
        `;
        return;
    }
    
    pendingActivitiesList.innerHTML = activities.map(activity => `
        <div class="pending-item ${activity.urgency}">
            <div class="title">${escapeHtml(activity.title)}</div>
            <div class="subject">${escapeHtml(activity.subject_name)} - ${escapeHtml(activity.course_code)}</div>
            <div class="due-date">
                <span class="badge ${getUrgencyBadgeClass(activity.urgency)}">${getRelativeTime(activity.due_date)}</span>
            </div>
        </div>
    `).join('');
}

/**
 * Escape HTML to prevent XSS
 * @param {string} str 
 * @returns {string}
 */
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Create a new class (faculty)
 */
async function handleCreateClass() {
    const subjectName = document.getElementById('subjectName')?.value.trim();
    const section = document.getElementById('section')?.value.trim();
    const courseCode = document.getElementById('courseCode')?.value.trim();
    const description = document.getElementById('classDescription')?.value.trim();
    
    if (!subjectName || !section || !courseCode) {
        showError('#createClassError', true, 'Please fill in all required fields');
        return;
    }
    
    setButtonLoading('createClassBtn', true);
    
    try {
        const response = await createClass({
            subject_name: subjectName,
            section: section,
            course_code: courseCode,
            description: description
        });
        
        if (response.success) {
            // Close create modal
            const createModal = bootstrap.Modal.getInstance(document.getElementById('createClassModal'));
            createModal?.hide();
            
            // Reset form
            document.getElementById('createClassForm')?.reset();
            
            // Show code modal
            const codeModal = new bootstrap.Modal(document.getElementById('classCodeModal'));
            document.getElementById('generatedCode').textContent = response.data.class.join_code;
            codeModal.show();
            
            // Reload classes
            loadClasses();
        }
    } catch (error) {
        showError('#createClassError', true, error.message);
    } finally {
        setButtonLoading('createClassBtn', false);
    }
}

/**
 * Join a class (student)
 */
async function handleJoinClass() {
    const joinCode = document.getElementById('joinCode')?.value.trim().toUpperCase();
    
    if (!joinCode || joinCode.length < 6) {
        showError('#joinClassError', true, 'Please enter a valid class code');
        return;
    }
    
    setButtonLoading('joinClassBtn', true);
    
    try {
        const response = await joinClass(joinCode);
        
        if (response.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('joinClassModal'));
            modal?.hide();
            
            // Reset form
            document.getElementById('joinClassForm')?.reset();
            
            // Show success
            showToast(`Successfully joined ${response.data.class.subject_name}!`, 'success');
            
            // Reload classes
            loadClasses();
            loadPendingActivities();
        }
    } catch (error) {
        showError('#joinClassError', true, error.message);
    } finally {
        setButtonLoading('joinClassBtn', false);
    }
}

/**
 * Copy join code to clipboard
 */
function copyJoinCode() {
    const code = document.getElementById('generatedCode')?.textContent;
    if (code) {
        navigator.clipboard.writeText(code).then(() => {
            showToast('Code copied to clipboard!', 'success');
        }).catch(() => {
            showToast('Failed to copy code', 'error');
        });
    }
}

/**
 * Show/hide error message
 */
function showError(selector, show, message = null) {
    const el = document.querySelector(selector);
    if (!el) return;
    
    if (message) el.textContent = message;
    el.style.display = show ? 'block' : 'none';
}

/**
 * Set button loading state
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

document.addEventListener('DOMContentLoaded', () => {
    if (!checkAuth()) return;
    
    updateRoleBasedUI();
    loadClasses();
    loadPendingActivities();
    
    // Hamburger toggle
    const toggleButton = document.querySelector('.nav-toggle');
    const navLinks = document.querySelector('.nav-links');
    if (toggleButton && navLinks) {
        toggleButton.addEventListener('click', () => {
            navLinks.classList.toggle('show');
        });
    }
});

// Create class button
document.getElementById('createClassBtn')?.addEventListener('click', handleCreateClass);

// Join class button
document.getElementById('joinClassBtn')?.addEventListener('click', handleJoinClass);

// Copy code button
document.getElementById('copyCodeBtn')?.addEventListener('click', copyJoinCode);

// Logout
document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
    e.preventDefault();
    await logoutUser();
});

// Enter key for forms
document.getElementById('createClassForm')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        handleCreateClass();
    }
});

document.getElementById('joinClassForm')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        handleJoinClass();
    }
});

// Auto-uppercase join code input
document.getElementById('joinCode')?.addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
