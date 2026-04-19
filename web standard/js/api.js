/**
 * API Service Module
 * Elixr University Classroom Management System
 * 
 * Handles all API communications with the PHP backend
 */

// API Configuration Constants
const API_BASE_URL = 'api';
const TOKEN_KEY = 'elixr_token';
const USER_KEY = 'elixr_user';

// HTTP Status Constants
const HTTP_OK = 200;
const HTTP_CREATED = 201;
const HTTP_UNAUTHORIZED = 401;

/**
 * Get stored auth token
 * @returns {string|null}
 */
function getAuthToken() {
    return localStorage.getItem(TOKEN_KEY);
}

/**
 * Set auth token
 * @param {string} token 
 */
function setAuthToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

/**
 * Remove auth token
 */
function removeAuthToken() {
    localStorage.removeItem(TOKEN_KEY);
}

/**
 * Get stored user data
 * @returns {object|null}
 */
function getStoredUser() {
    const userData = localStorage.getItem(USER_KEY);
    try {
        return userData ? JSON.parse(userData) : null;
    } catch (e) {
        return null;
    }
}

/**
 * Set user data
 * @param {object} user 
 */
function setStoredUser(user) {
    localStorage.setItem(USER_KEY, JSON.stringify(user));
}

/**
 * Remove user data
 */
function removeStoredUser() {
    localStorage.removeItem(USER_KEY);
}

/**
 * Check if user is logged in
 * @returns {boolean}
 */
function isLoggedIn() {
    return !!getAuthToken() && !!getStoredUser();
}

/**
 * Get current user role
 * @returns {string|null}
 */
function getUserRole() {
    const user = getStoredUser();
    return user ? user.role : null;
}

/**
 * Check if current user is faculty
 * @returns {boolean}
 */
function isFaculty() {
    return getUserRole() === 'faculty';
}

/**
 * Check if current user is student
 * @returns {boolean}
 */
function isStudent() {
    return getUserRole() === 'student';
}

/**
 * Make API request
 * @param {string} endpoint 
 * @param {object} options 
 * @returns {Promise<object>}
 */
async function apiRequest(endpoint, options = {}) {
    const url = `${API_BASE_URL}/${endpoint}`;
    const token = getAuthToken();
    
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };
    
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    
    try {
        const response = await fetch(url, {
            ...options,
            headers
        });
        
        const data = await response.json();
        
        // Handle unauthorized - redirect to login
        if (response.status === HTTP_UNAUTHORIZED) {
            removeAuthToken();
            removeStoredUser();
            window.location.href = getBasePath() + 'student.html';
            throw new Error('Session expired. Please login again.');
        }
        
        if (!response.ok) {
            throw new Error(data.message || 'An error occurred');
        }
        
        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

/**
 * Get base path for redirects
 * @returns {string}
 */
function getBasePath() {
    const path = window.location.pathname;
    if (path.includes('/asset/page/')) {
        return '../../';
    }
    return '';
}

// ==================== AUTH API ====================

/**
 * Register new user
 * @param {object} userData 
 * @returns {Promise<object>}
 */
async function registerUser(userData) {
    try {
        const response = await apiRequest('auth/register.php', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
        
        if (response.success) {
            setAuthToken(response.data.token);
            setStoredUser(response.data.user);
        }
        
        return response;
    } catch (error) {
        throw error;
    }
}

/**
 * Login user
 * @param {string} username 
 * @param {string} password 
 * @returns {Promise<object>}
 */
async function loginUser(username, password) {
    try {
        const response = await apiRequest('auth/login.php', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });
        
        if (response.success) {
            setAuthToken(response.data.token);
            setStoredUser(response.data.user);
        }
        
        return response;
    } catch (error) {
        throw error;
    }
}

/**
 * Logout user
 * @returns {Promise<object>}
 */
async function logoutUser() {
    try {
        await apiRequest('auth/logout.php', { method: 'POST' });
    } catch (error) {
        console.error('Logout error:', error);
    } finally {
        removeAuthToken();
        removeStoredUser();
        window.location.href = getBasePath() + 'student.html';
    }
}

/**
 * Get current user profile
 * @returns {Promise<object>}
 */
async function getCurrentUser() {
    return apiRequest('auth/me.php');
}

// ==================== CLASSES API ====================

/**
 * Get all classes for current user
 * @returns {Promise<object>}
 */
async function getClasses() {
    return apiRequest('classes/');
}

/**
 * Create new class (faculty only)
 * @param {object} classData 
 * @returns {Promise<object>}
 */
async function createClass(classData) {
    return apiRequest('classes/', {
        method: 'POST',
        body: JSON.stringify(classData)
    });
}

/**
 * Get class details
 * @param {number} classId 
 * @returns {Promise<object>}
 */
async function getClassDetail(classId) {
    return apiRequest(`classes/detail.php?id=${classId}`);
}

/**
 * Join class with code (student only)
 * @param {string} joinCode 
 * @returns {Promise<object>}
 */
async function joinClass(joinCode) {
    return apiRequest('classes/join.php', {
        method: 'POST',
        body: JSON.stringify({ join_code: joinCode })
    });
}

/**
 * Get students in a class (faculty only)
 * @param {number|null} classId 
 * @returns {Promise<object>}
 */
async function getClassStudents(classId = null) {
    const url = classId ? `classes/students.php?id=${classId}` : 'classes/students.php';
    return apiRequest(url);
}

/**
 * Get people in a class (classmates + instructor)
 * @param {number} classId 
 * @returns {Promise<object>}
 */
async function getClassPeople(classId) {
    return apiRequest(`classes/people.php?id=${classId}`);
}

// ==================== ANNOUNCEMENTS API ====================

/**
 * Get announcements
 * @param {number|null} classId 
 * @returns {Promise<object>}
 */
async function getAnnouncements(classId = null) {
    const url = classId ? `announcements/?class_id=${classId}` : 'announcements/';
    return apiRequest(url);
}

/**
 * Create announcement (faculty only)
 * @param {object} announcementData 
 * @returns {Promise<object>}
 */
async function createAnnouncement(announcementData) {
    return apiRequest('announcements/', {
        method: 'POST',
        body: JSON.stringify(announcementData)
    });
}

// ==================== CLASSWORK API ====================

/**
 * Get classwork
 * @param {number|null} classId 
 * @param {string|null} type 
 * @returns {Promise<object>}
 */
async function getClasswork(classId = null, type = null) {
    let url = 'classwork/';
    const params = [];
    if (classId) params.push(`class_id=${classId}`);
    if (type) params.push(`type=${type}`);
    if (params.length) url += '?' + params.join('&');
    return apiRequest(url);
}

/**
 * Create classwork (faculty only)
 * @param {object} classworkData 
 * @returns {Promise<object>}
 */
async function createClasswork(classworkData) {
    return apiRequest('classwork/', {
        method: 'POST',
        body: JSON.stringify(classworkData)
    });
}

/**
 * Get pending activities (student only)
 * @param {number} limit 
 * @returns {Promise<object>}
 */
async function getPendingActivities(limit = 10) {
    return apiRequest(`classwork/pending.php?limit=${limit}`);
}

/**
 * Get calendar events (student only)
 * @param {string} month 
 * @returns {Promise<object>}
 */
async function getCalendarEvents(month = null) {
    const url = month ? `classwork/calendar.php?month=${month}` : 'classwork/calendar.php';
    return apiRequest(url);
}

// ==================== SUBMISSIONS API ====================

/**
 * Get submissions for classwork (faculty only)
 * @param {number} classworkId 
 * @returns {Promise<object>}
 */
async function getSubmissions(classworkId) {
    return apiRequest(`submissions/?classwork_id=${classworkId}`);
}

/**
 * Submit work (student only)
 * @param {object} submissionData 
 * @returns {Promise<object>}
 */
async function submitWork(submissionData) {
    return apiRequest('submissions/', {
        method: 'POST',
        body: JSON.stringify(submissionData)
    });
}

/**
 * Grade submission (faculty only)
 * @param {object} gradeData 
 * @returns {Promise<object>}
 */
async function gradeSubmission(gradeData) {
    return apiRequest('submissions/', {
        method: 'PUT',
        body: JSON.stringify(gradeData)
    });
}

// ==================== UI HELPERS ====================

/**
 * Show toast notification
 * @param {string} message 
 * @param {string} type 
 */
function showToast(message, type = 'info') {
    // Create toast container if not exists
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999;';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show`;
    toast.style.cssText = 'min-width: 250px; margin-bottom: 10px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

/**
 * Format date for display
 * @param {string} dateStr 
 * @returns {string}
 */
function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Format datetime for display
 * @param {string} dateStr 
 * @returns {string}
 */
function formatDateTime(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Get relative time string
 * @param {string} dateStr 
 * @returns {string}
 */
function getRelativeTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diff = date - now;
    const days = Math.ceil(diff / (1000 * 60 * 60 * 24));
    
    if (days < 0) return `${Math.abs(days)} days overdue`;
    if (days === 0) return 'Due today';
    if (days === 1) return 'Due tomorrow';
    if (days <= 7) return `Due in ${days} days`;
    return formatDate(dateStr);
}

/**
 * Get urgency badge class
 * @param {string} urgency 
 * @returns {string}
 */
function getUrgencyBadgeClass(urgency) {
    const classes = {
        'overdue': 'bg-danger',
        'urgent': 'bg-warning text-dark',
        'soon': 'bg-info',
        'upcoming': 'bg-secondary'
    };
    return classes[urgency] || 'bg-secondary';
}

/**
 * Get type badge class
 * @param {string} type 
 * @returns {string}
 */
function getTypeBadgeClass(type) {
    const classes = {
        'lesson': 'bg-primary',
        'lab': 'bg-success',
        'quiz': 'bg-warning text-dark',
        'assignment': 'bg-info'
    };
    return classes[type] || 'bg-secondary';
}
