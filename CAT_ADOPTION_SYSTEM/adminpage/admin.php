<?php
session_start();

/* ==============================
   ADMIN LOGOUT
============================== */
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header("Location: ../login%20page/login.html");
    exit;
}

/* ==============================
   ADMIN AUTHENTICATION
============================== */
if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login%20page/login.html");
    exit;
}

$adminName = htmlspecialchars(
    $_SESSION["admin_username"] ?? "Admin",
    ENT_QUOTES,
    "UTF-8"
);

$adminInitial = strtoupper(
    substr($_SESSION["admin_username"] ?? "A", 0, 1)
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>PurrFect Home | Admin Dashboard</title>

    <link rel="stylesheet" href="admin.css">
      <script src="admin.js?v=7"></script>

    <style>
        /* ==============================
           HEADER LOGOUT BUTTON
        ============================== */

        .header-logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 15px;
            border: 1px solid #ff3298;
            border-radius: 10px;
            background: #fff0f7;
            color: #e91e83;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: 0.2s ease;
            flex-shrink: 0;
        }

        .header-logout-btn:hover {
            background: #ff3298;
            color: #ffffff;
        }

        .header-logout-btn .logout-icon {
            font-size: 17px;
            line-height: 1;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        @media (max-width: 600px) {
            .topbar-right {
                gap: 7px;
            }

            .header-logout-btn {
                padding: 8px 9px;
                gap: 4px;
                font-size: 10px;
            }
        }

        /* ==============================
           PRINT REPORT
        ============================== */

        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }

            html,
            body {
                width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                color: #222222 !important;
                font-family: Arial, sans-serif !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Hide dashboard navigation and controls */
            .sidebar,
            .topbar,
            .page-footer,
            .toast,
            .modal-overlay,
            .menu-toggle,
            .btn,
            .text-btn,
            .header-logout-btn {
                display: none !important;
            }

            .app,
            .main-area,
            .content {
                display: block !important;
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #ffffff !important;
                box-shadow: none !important;
                transform: none !important;
            }

            /* Print only Reports & Statistics */
            .page-section {
                display: none !important;
                visibility: hidden !important;
            }

            #page-reports {
                display: block !important;
                visibility: visible !important;
                position: static !important;
                width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #ffffff !important;
                color: #222222 !important;
            }

            #page-reports * {
                visibility: visible !important;
            }

            #page-reports .section-heading {
                display: block !important;
                margin: 0 0 20px !important;
                padding: 0 0 12px !important;
                border-bottom: 2px solid #ff3298 !important;
            }

            #page-reports .section-heading h2 {
                margin: 0 0 6px !important;
                font-size: 24px !important;
                color: #222222 !important;
            }

            #page-reports .section-heading p {
                margin: 0 !important;
                font-size: 12px !important;
                color: #555555 !important;
            }

            #page-reports .report-cards {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: 10px !important;
                margin: 20px 0 !important;
            }

            #page-reports .report-card,
            #page-reports .panel {
                background: #ffffff !important;
                color: #222222 !important;
                border: 1px solid #dddddd !important;
                box-shadow: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            #page-reports .report-card {
                padding: 12px !important;
            }

            #page-reports .report-card strong {
                font-size: 23px !important;
                color: #222222 !important;
            }

            #page-reports .report-grid {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 12px !important;
            }

            #page-reports .report-panel {
                padding: 14px !important;
            }

            #page-reports h3,
            #page-reports p,
            #page-reports span,
            #page-reports b,
            #page-reports strong,
            #page-reports li {
                color: #222222;
            }

            #page-reports .pie,
            #page-reports .progress-track,
            #page-reports .progress-track span,
            #page-reports .pie-legend i {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            #page-reports .progress-item {
                margin-bottom: 12px;
                break-inside: avoid;
            }

            #page-reports .progress-track {
                border: 1px solid #cccccc;
            }
        }
    </style>
</head>

<body>

<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">

        <div class="brand">
            <div class="brand-logo">🐾</div>

            <div>
                <h2>PurrFect <span>Home</span></h2>
                <small>ADMINISTRATION</small>
            </div>

            <button
                class="close-sidebar"
                id="closeSidebar"
                aria-label="Close menu"
            >×</button>
        </div>

        <div class="admin-profile">
            <div class="avatar" id="adminAvatar">
                <?php echo $adminInitial; ?>
            </div>

            <div>
                <strong id="sidebarAdminName">
                    <?php echo $adminName; ?>
                </strong>
                <small>Administrator</small>
            </div>

            <span class="online-dot"></span>
        </div>

        <p class="menu-label">MAIN MENU</p>

        <nav class="sidebar-nav">

            <button class="nav-item active" data-page="dashboard">
                <span class="nav-icon">▦</span>
                Dashboard
            </button>

            <button class="nav-item" data-page="cats">
                <span class="nav-icon"></span>
                Manage Cats
            </button>

            <button class="nav-item" data-page="applications">
                <span class="nav-icon"></span>
                Review Applications
                <span class="menu-count" id="pendingBadge">0</span>
            </button>

            <button class="nav-item" data-page="found">
                <span class="nav-icon"></span>
                Found Cat
            </button>

            <button class="nav-item" data-page="students">
                <span class="nav-icon"></span>
                Student List
            </button>

            <p class="menu-label">MANAGEMENT</p>

            <button class="nav-item" data-page="reports">
                <span class="nav-icon">📊</span>
                Reports &amp; Statistics
            </button>

            <button class="nav-item" data-page="rewards">
                <span class="nav-icon">🎁</span>
                Student Rewards
            </button>

        </nav>

        <div class="sidebar-bottom">

            <div class="sidebar-tip">
                <span>💗</span>
                <strong>Every cat deserves a home.</strong>
                <p>Thank you for making a difference.</p>
            </div>

            <a
                class="nav-item logout-btn"
                id="logoutBtn"
                href="?logout=1"
                style="text-decoration:none;"
            >
                <span class="nav-icon">↪</span>
                Log Out
            </a>

            <small class="sidebar-version">
                PURRFECT HOME · ADMIN PANEL
            </small>

        </div>
    </aside>

    <!-- MAIN AREA -->
    <main class="main-area">

        <!-- HEADER -->
        <header class="topbar">

            <div class="topbar-left">

                <button
                    class="menu-toggle"
                    id="menuToggle"
                    aria-label="Open menu"
                >&#8942;</button>

                <div>
                    <div class="breadcrumb">
                        PurrFect Home <span>/</span>
                        <b id="breadcrumbTitle">Dashboard</b>
                    </div>

                    <h1 id="pageTitle">Dashboard Overview</h1>
                </div>

            </div>

            <div class="topbar-right">

                <div class="today-date" id="todayDate"></div>

                <div class="top-avatar" id="topAvatar">
                    <?php echo $adminInitial; ?>
                </div>

                <!-- HEADER LOGOUT -->
                <a
                    href="?logout=1"
                    class="header-logout-btn"
                    id="headerLogoutBtn"
                    title="Log out of your admin account"
                >
                    <span class="logout-icon"></span>
                    LOG OUT
                </a>

            </div>
        </header>

        <div class="content">

            <!-- DASHBOARD -->
            <section class="page-section active" id="page-dashboard">

                <div class="welcome-banner">
                    <div>
                        <span class="welcome-tag">ADMIN WORKSPACE</span>

                        <h2>
                            Welcome back,
                            <span id="welcomeName"><?php echo $adminName; ?></span>!
                            <span>♡</span>
                        </h2>

                        <p>
                            Here is what's happening at PurrFect Home today.
                        </p>
                    </div>

                    <div class="welcome-art" aria-hidden="true">🐈</div>
                </div>

                <div class="section-heading">
                    <div>
                        <h2>System Overview</h2>
                        <p>Quick summary of your adoption system</p>
                    </div>

                    <button class="btn btn-outline" id="refreshBtn">
                        ↻ Refresh View
                    </button>
                </div>

                <article class="panel overview-panel">
                    <div class="table-wrap">
                        <table class="overview-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                            <tbody id="overviewTable">
                                <tr>
                                    <td colspan="2">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <div class="dashboard-grid">

                    <article class="panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Recent Applications</h3>
                                <p>Latest adoption requests</p>
                            </div>

                            <button class="text-btn" data-goto="applications">
                                View all →
                            </button>
                        </div>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Cat</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>

                                <tbody id="recentApplications">
                                    <tr>
                                        <td colspan="4">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="panel">
                        <div class="panel-heading">
                            <div>
                                <h3>Cat Availability</h3>
                                <p>Illustrative status summary</p>
                            </div>
                        </div>

                        <div class="availability-list">

                            <div class="availability-row">
                                <span class="availability-symbol available-symbol">🐾</span>

                                <div class="availability-info">
                                    <strong>Available</strong>
                                    <small>Ready for adoption</small>
                                </div>

                                <strong id="availableCats">0</strong>
                            </div>

                            <div class="availability-row">
                                <span class="availability-symbol pending-symbol">♡</span>

                                <div class="availability-info">
                                    <strong>Reserved / Pending</strong>
                                    <small>Applications in progress</small>
                                </div>

                                <strong id="reservedCats">0</strong>
                            </div>

                            <div class="availability-row">
                                <span class="availability-symbol adopted-symbol">✓</span>

                                <div class="availability-info">
                                    <strong>Adopted</strong>
                                    <small>Forever homes found</small>
                                </div>

                                <strong id="adoptedCats">0</strong>
                            </div>

                        </div>

                        <button class="btn btn-primary full-width" data-goto="cats">
                            Manage Cat Records
                        </button>
                    </article>

                </div>

                <article class="panel quick-panel">
                    <div class="panel-heading">
                        <div>
                            <h3>Quick Actions</h3>
                            <p>Common administrative tasks</p>
                        </div>
                    </div>

                    <div class="quick-actions">

                        <button class="quick-action" data-goto="cats">
                            <span class="quick-action-icon">＋</span>
                            <span>
                                <strong>Manage Cats</strong>
                                <small>Add or update cat records</small>
                            </span>
                            <span class="quick-arrow">→</span>
                        </button>

                        <button class="quick-action" data-goto="applications">
                            <span class="quick-action-icon purple-bg">✓</span>
                            <span>
                                <strong>Review Applications</strong>
                                <small>Check pending requests</small>
                            </span>
                            <span class="quick-arrow">→</span>
                        </button>

                        <button class="quick-action" data-goto="found">
                            <span class="quick-action-icon peach-bg">⌕</span>
                            <span>
                                <strong>Found Cat Reports</strong>
                                <small>Review submitted reports</small>
                            </span>
                            <span class="quick-arrow">→</span>
                        </button>

                    </div>
                </article>

            </section>

            <!-- MANAGE CATS -->
            <section class="page-section" id="page-cats">

                <div class="section-heading">
                    <div>
                        <h2>Manage Cats</h2>
                        <p>Maintain the cat records in your adoption system.</p>
                    </div>

                    <button class="btn btn-primary" id="addCatBtn">
                        ＋ Add New Cat
                    </button>
                </div>

                <article class="panel">
                    <div class="table-toolbar">
                        <input
                            class="search-input"
                            id="catSearch"
                            type="search"
                            placeholder="Search cat name..."
                        >

                        <select id="catStatusFilter" class="filter-select">
                            <option value="">All statuses</option>
                            <option value="Available">Available</option>
                            <option value="Pending">Pending</option>
                            <option value="Adopted">Adopted</option>
                        </select>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Cat ID</th>
                                    <th>Cat Name</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody id="catsTable"></tbody>
                        </table>
                    </div>
                </article>

            </section>

            <!-- APPLICATIONS -->
            <section class="page-section" id="page-applications">

                <div class="section-heading">
                    <div>
                        <h2>Review Applications</h2>
                        <p>Review student requests to adopt cats.</p>
                    </div>
                </div>

                <article class="panel">
                    <div class="table-toolbar">
                        <input
                            class="search-input"
                            id="applicationSearch"
                            type="search"
                            placeholder="Search student or cat..."
                        >

                        <select id="applicationStatusFilter" class="filter-select">
                            <option value="">All statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Application ID</th>
                                    <th>Student</th>
                                    <th>Cat</th>
                                    <th>Date Applied</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody id="applicationsTable"></tbody>
                        </table>
                    </div>
                </article>

            </section>

            <!-- FOUND CATS -->
            <section class="page-section" id="page-found">

                <div class="section-heading">
                    <div>
                        <h2>Found Cat</h2>
                        <p>Review reports about cats found by students.</p>
                    </div>
                </div>

                <article class="panel">
                    <div class="table-toolbar">
                        <input
                            class="search-input"
                            id="foundSearch"
                            type="search"
                            placeholder="Search location or reporter..."
                        >
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Reported By</th>
                                    <th>Location</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody id="foundTable"></tbody>
                        </table>
                    </div>
                </article>

            </section>

            <!-- STUDENTS -->
            <section class="page-section" id="page-students">

                <div class="section-heading">
                    <div>
                        <h2>Student List</h2>
                        <p>
                            Students who have adopted a cat or reported a found cat.
                            The student who finds the most cats earns a reward.
                        </p>
                    </div>
                </div>

                <article class="panel">
                    <div class="table-toolbar">
                        <input
                            class="search-input"
                            id="studentSearch"
                            type="search"
                            placeholder="Search student name or email..."
                        >
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Student Email</th>
                                    <th>Account Status</th>
                                    <th>Adopted</th>
                                    <th>Found Reports</th>
                                </tr>
                            </thead>

                            <tbody id="studentsTable"></tbody>
                        </table>
                    </div>
                </article>

            </section>

            <!-- REPORTS -->
            <section class="page-section" id="page-reports">

                <div class="section-heading">
                    <div>
                        <h2>Reports &amp; Statistics</h2>
                        <p>A quick overview of adoption activity.</p>
                    </div>

                    <!-- PRINT BUTTON -->
                    <button
                        type="button"
                        class="btn btn-outline"
                        id="printReportBtn"
                        onclick="window.print();"
                    >
                        ⎙ Print Report
                    </button>
                </div>

                <div class="report-cards">

                    <div class="report-card">
                        <span>Total Cats</span>
                        <strong id="repTotalCats">0</strong>
                    </div>

                    <div class="report-card green">
                        <span>Approved</span>
                        <strong id="repApproved">0</strong>
                    </div>

                    <div class="report-card orange">
                        <span>Pending</span>
                        <strong id="repPending">0</strong>
                    </div>

                    <div class="report-card blue">
                        <span>Found Report</span>
                        <strong id="repFound">0</strong>
                    </div>

                </div>

                <div class="report-grid">

                    <article class="panel report-panel">
                        <h3>Adoption Progress</h3>
                        <p>Share of all adoption applications by status.</p>

                        <div class="pie-wrap">
                            <div
                                class="pie"
                                id="adoptionPie"
                                role="img"
                                aria-label="Adoption progress pie chart"
                            >
                                <span id="pieTotal">0</span>
                            </div>

                            <ul class="pie-legend">
                                <li>
                                    <i style="background:#198754"></i>
                                    Approved
                                    <b id="legApproved">0</b>
                                </li>

                                <li>
                                    <i style="background:#e6a11a"></i>
                                    Pending
                                    <b id="legPending">0</b>
                                </li>

                                <li>
                                    <i style="background:#ff2796"></i>
                                    Rejected
                                    <b id="legRejected">0</b>
                                </li>
                            </ul>
                        </div>
                    </article>

                    <article class="panel report-panel">
                        <h3>Application Status</h3>
                        <p>Bar chart of the same figures.</p>

                        <div class="progress-item">
                            <div>
                                <span>Approved</span>
                                <strong id="barApprovedN">0</strong>
                            </div>

                            <div class="progress-track">
                                <span
                                    id="barApproved"
                                    style="width:0%;background:#198754"
                                ></span>
                            </div>
                        </div>

                        <div class="progress-item">
                            <div>
                                <span>Pending</span>
                                <strong id="barPendingN">0</strong>
                            </div>

                            <div class="progress-track">
                                <span
                                    id="barPending"
                                    style="width:0%;background:#e6a11a"
                                ></span>
                            </div>
                        </div>

                        <div class="progress-item">
                            <div>
                                <span>Rejected</span>
                                <strong id="barRejectedN">0</strong>
                            </div>

                            <div class="progress-track">
                                <span
                                    id="barRejected"
                                    style="width:0%;background:#ff2796"
                                ></span>
                            </div>
                        </div>

                    </article>

                </div>

            </section>

            <!-- REWARDS -->
            <section class="page-section" id="page-rewards">

                <div class="section-heading">
                    <div>
                        <h2>Student Rewards</h2>
                        <p>
                            Students who report the most found cats are eligible for a reward.
                        </p>
                    </div>
                </div>

                <div class="info-note">
                    Eligible students claim their reward via WhatsApp.
                    After giving the reward, press <b>Mark Given</b>.
                </div>

                <article class="panel">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Student Email</th>
                                    <th>Found Reports</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody id="rewardsTable"></tbody>
                        </table>
                    </div>
                </article>

            </section>

            <!-- FOOTER -->
            <footer class="page-footer">
                <span>© 2026 PurrFect Home Cat Adoption System</span>
                <span>Made with <b>♥</b> for every cat</span>
            </footer>

        </div>
    </main>
</div>

<!-- ADD CAT MODAL -->
<div class="modal-overlay" id="catModal" aria-hidden="true">

    <div class="modal">

        <div class="modal-heading">
            <div>
                <h2>Add New Cat</h2>
                <p>Enter details for the preview table.</p>
            </div>

            <button
                type="button"
                class="modal-close"
                id="closeCatModal"
                aria-label="Close"
            >×</button>
        </div>

        <form id="catForm">

            <label for="newCatName">Cat Name</label>
            <input
                id="newCatName"
                required
                maxlength="60"
                placeholder="e.g. Milo"
            >

            <div class="form-row">

                <div>
                    <label for="newCatAge">Age</label>
                    <input
                        id="newCatAge"
                        required
                        placeholder="e.g. 1 year"
                    >
                </div>

                <div>
                    <label for="newCatGender">Gender</label>
                    <select id="newCatGender">
                        <option>Male</option>
                        <option>Female</option>
                        <option>Unknown</option>
                    </select>
                </div>

            </div>

            <label for="newCatStatus">Status</label>

            <select id="newCatStatus">
                <option>Available</option>
                <option>Pending</option>
                <option>Adopted</option>
            </select>

            <div class="modal-actions">

                <button
                    type="button"
                    class="btn btn-outline"
                    id="cancelCatBtn"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Add Cat
                </button>

            </div>

        </form>

    </div>
</div>

<div class="toast" id="toast" role="status"></div>

</body>
</html>