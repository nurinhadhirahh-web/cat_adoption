document.addEventListener("DOMContentLoaded", () => {
    const API = "admin_api.php";
    const LOGOUT_URL = "../login%20page/logout.php";
    const LOGIN_URL = "../login%20page/login.html";

    // Bilangan minimum found report untuk layak dapat reward.
    // Mesti sama dengan syarat di halaman pelajar (4 laporan).
    const REWARD_MIN_FOUND = 4;

    const sidebar = document.getElementById("sidebar");
    const menuToggle = document.getElementById("menuToggle");
    const closeSidebar = document.getElementById("closeSidebar");
    const toast = document.getElementById("toast");

    const pageNames = {
        dashboard: "Dashboard Overview",
        cats: "Manage Cats",
        applications: "Review Applications",
        found: "Found Cat",
        students: "Student List",
        reports: "Reports & Statistics",
        rewards: "Student Rewards"
    };

    let adminName = "admin";
    let cats = [];
    let applications = [];
    let foundReports = [];
    let students = [];
    let toastTimer;

    // ---------- GENERAL HELPERS ----------

    function showToast(message) {
        if (!toast) {
            alert(message);
            return;
        }

        toast.textContent = message;
        toast.classList.add("show");

        clearTimeout(toastTimer);

        toastTimer = setTimeout(() => {
            toast.classList.remove("show");
        }, 3000);
    }

    function escapeHTML(value) {
        return String(value ?? "").replace(/[&<>"']/g, char => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        })[char]);
    }

    function badge(status) {
        const value = String(status || "Pending");
        const className = value.toLowerCase().replace(/[^a-z]/g, "");

        return `<span class="badge ${className}">${escapeHTML(value)}</span>`;
    }

    function setText(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value ?? "";
    }

    // ---------- API CONNECTION ----------

    async function api(body = null, action = "dashboard") {
        const options = {
            method: body ? "POST" : "GET",
            credentials: "same-origin",
            headers: {
                "X-Requested-With": "fetch",
                "Accept": "application/json"
            }
        };

        let url = API;

        if (body) {
            options.headers["Content-Type"] = "application/json";
            options.body = JSON.stringify(body);
        } else {
            url += "?action=" + encodeURIComponent(action);
        }

        const response = await fetch(url, options);

        if (response.status === 401) {
            window.location.href = LOGIN_URL;
            return null;
        }

        const raw = await response.text();
        let result;

        try {
            result = JSON.parse(raw);
        } catch (error) {
            console.error("Invalid API response:", raw);
            throw new Error(
                "Server did not return valid JSON. Check admin_api.php for PHP errors."
            );
        }

        if (!response.ok || result.success === false) {
            throw new Error(
                result.message || `Request failed (${response.status}).`
            );
        }

        return result;
    }

    async function loadData() {
        try {
            const result = await api(null, "dashboard");

            if (!result) return;

            const data = result.data || {};

            adminName = data.admin || "admin";
            cats = Array.isArray(data.cats) ? data.cats : [];
            applications = Array.isArray(data.applications)
                ? data.applications : [];
            foundReports = Array.isArray(data.found)
                ? data.found : [];
            students = Array.isArray(data.students)
                ? data.students : [];

            renderAll();
        } catch (error) {
            console.error("Dashboard loading error:", error);
            showToast(error.message || "Unable to load dashboard data.");
        }
    }

    async function send(body, successMessage) {
        try {
            const result = await api(body);

            if (result && result.success) {
                showToast(result.message || successMessage);
                await loadData();
                return true;
            }

            showToast(result?.message || "Action failed.");
            return false;
        } catch (error) {
            console.error("API action error:", error);
            showToast(error.message || "Action failed. Please try again.");
            return false;
        }
    }

    // ---------- NAVIGATION ----------

    function setPage(page) {
        if (!pageNames[page]) return;

        document.querySelectorAll(".page-section").forEach(section => {
            section.classList.toggle(
                "active",
                section.id === `page-${page}`
            );
        });

        document.querySelectorAll(".nav-item[data-page]").forEach(item => {
            item.classList.toggle("active", item.dataset.page === page);
        });

        setText("pageTitle", pageNames[page]);
        setText(
            "breadcrumbTitle",
            pageNames[page].replace(" Overview", "")
        );

        if (sidebar) sidebar.classList.remove("open");

        window.scrollTo({ top: 0, behavior: "smooth" });
        history.replaceState(null, "", `#${page}`);
    }

    document.querySelectorAll(".nav-item[data-page]").forEach(item => {
        item.addEventListener("click", () => {
            setPage(item.dataset.page);
        });
    });

    document.querySelectorAll("[data-goto]").forEach(button => {
        button.addEventListener("click", () => {
            setPage(button.dataset.goto);
        });
    });

    if (menuToggle && sidebar) {
        menuToggle.addEventListener("click", () => {
            sidebar.classList.add("open");
        });
    }

    if (closeSidebar && sidebar) {
        closeSidebar.addEventListener("click", () => {
            sidebar.classList.remove("open");
        });
    }

    document.addEventListener("click", event => {
        if (
            window.innerWidth <= 950 &&
            sidebar &&
            menuToggle &&
            sidebar.classList.contains("open") &&
            !sidebar.contains(event.target) &&
            !menuToggle.contains(event.target)
        ) {
            sidebar.classList.remove("open");
        }
    });

    setText(
        "todayDate",
        new Intl.DateTimeFormat("en-MY", {
            day: "numeric",
            month: "short",
            year: "numeric"
        }).format(new Date())
    );

    // ---------- RENDER FUNCTIONS ----------

    function countBy(list, status) {
        return list.filter(item =>
            String(item.status || "").toLowerCase() === status.toLowerCase()
        ).length;
    }

    function renderAdmin() {
        const initial = adminName.charAt(0).toUpperCase() || "A";

        setText("welcomeName", adminName);
        setText("sidebarAdminName", adminName);
        setText("adminAvatar", initial);
        setText("topAvatar", initial);
    }

    function renderOverview() {
        const rows = [
            ["Total cats", cats.length],
            ["Total applications", applications.length],
            ["Pending applications", countBy(applications, "Pending")],
            ["Approved applications", countBy(applications, "Approved")],
            ["Found cat reports", foundReports.length],
            ["Registered students", students.length]
        ];

        const overviewTable = document.getElementById("overviewTable");

        if (overviewTable) {
            overviewTable.innerHTML = rows.map(row => `
                <tr>
                    <td>${escapeHTML(row[0])}</td>
                    <td class="num">${row[1]}</td>
                </tr>
            `).join("");
        }

        setText("pendingBadge", countBy(applications, "Pending"));
        setText("availableCats", countBy(cats, "Available"));
        setText("reservedCats", countBy(cats, "Pending"));
        setText("adoptedCats", countBy(cats, "Adopted"));
    }

    function renderCats() {
        const table = document.getElementById("catsTable");
        if (!table) return;

        const query = (
            document.getElementById("catSearch")?.value || ""
        ).toLowerCase();

        const statusFilter =
            document.getElementById("catStatusFilter")?.value || "";

        const filtered = cats.filter(cat =>
            `${cat.id} ${cat.name} ${cat.gender}`
                .toLowerCase()
                .includes(query) &&
            (!statusFilter || cat.status === statusFilter)
        );

        table.innerHTML = filtered.length
            ? filtered.map(cat => `
                <tr>
                    <td>${escapeHTML(cat.id)}</td>
                    <td><strong>${escapeHTML(cat.name)}</strong></td>
                    <td>${escapeHTML(cat.age)}</td>
                    <td>${escapeHTML(cat.gender)}</td>
                    <td>${badge(cat.status)}</td>
                    <td>
                        <div class="action-group">
                            <select
                                class="status-select"
                                data-status="${escapeHTML(String(cat.status).toLowerCase())}"
                                data-cat-id="${escapeHTML(cat.id)}"
                                aria-label="Change status for ${escapeHTML(cat.name)}">
                                ${["Available", "Pending", "Adopted"].map(status => `
                                    <option value="${status}"
                                        ${status === cat.status ? "selected" : ""}>
                                        ${status}
                                    </option>
                                `).join("")}
                            </select>
                        </div>
                    </td>
                </tr>
            `).join("")
            : `<tr><td colspan="6">No matching cats found.</td></tr>`;
    }

    // Buttons shown in the Review Applications table, depending on status.
    // Pending  : Approve + Reject only
    // Approved : View Details + Set Pickup / Edit Pickup
    // Rejected : View Details only (read-only record)
    function applicationActionButtons(app) {
        const status = String(app.status || "Pending").toLowerCase();
        const id = escapeHTML(app.id);

        const viewButton = `
            <button type="button"
                class="action-btn view-details-btn"
                data-view-application="${id}">
                View Details
            </button>`;

        if (status === "approved") {
            const hasPickup = app.pickup_date && app.pickup_time;

            // Cat already handed over: no more pickup actions.
            if (app.picked_up_at) {
                return viewButton + `
            <button type="button"
                class="action-btn approve"
                disabled>
                Picked Up
            </button>`;
            }

            return viewButton + `
            <button type="button"
                class="action-btn pickup-edit-btn"
                data-pickup-edit="${id}">
                ${hasPickup ? "Edit Pickup" : "Set Pickup"}
            </button>` + (hasPickup ? `
            <button type="button"
                class="action-btn approve"
                data-mark-picked="${id}">
                Mark as Picked Up
            </button>` : "");
        }

        if (status === "rejected") {
            return viewButton;
        }

        return `
            <button type="button"
                class="action-btn approve"
                data-app-action="Approved"
                data-app-id="${id}">
                Approve
            </button>
            <button type="button"
                class="action-btn reject"
                data-app-action="Rejected"
                data-app-id="${id}">
                Reject
            </button>`;
    }

    function renderApplications() {
        const table = document.getElementById("applicationsTable");
        if (!table) return;

        const query = (
            document.getElementById("applicationSearch")?.value || ""
        ).toLowerCase();

        const statusFilter =
            document.getElementById("applicationStatusFilter")?.value || "";

        const filtered = applications.filter(app =>
            `${app.id} ${app.student} ${app.email} ${app.cat}`
                .toLowerCase()
                .includes(query) &&
            (!statusFilter || app.status === statusFilter)
        );

        // Main Applications Table
        table.innerHTML = filtered.length
            ? filtered.map(app => `
                <tr>
                    <td>${escapeHTML(app.id)}</td>
                    <td>
                        <strong>${escapeHTML(app.student)}</strong><br>
                        <small class="muted">${escapeHTML(app.email)}</small>
                    </td>
                    <td>${escapeHTML(app.cat)}</td>
                    <td>${escapeHTML(app.date)}</td>
                    <td>${badge(app.status)}</td>
                    <td>
                        <div class="action-group">
                            ${applicationActionButtons(app)}
                        </div>
                    </td>
                </tr>
            `).join("")
            : `<tr><td colspan="6">No matching applications found.</td></tr>`;

        // Recent Applications Table
        const recentTable = document.getElementById("recentApplications");

        if (recentTable) {
            const recent = applications.slice(0, 5);

            recentTable.innerHTML = recent.length
                ? recent.map(app => `
                    <tr>
                        <td>
                            <strong>${escapeHTML(app.student)}</strong>
                        </td>
                        <td>${escapeHTML(app.cat)}</td>
                        <td>${escapeHTML(app.date)}</td>
                        <td>
                            <select
                                class="filter-select app-status-select"
                                data-app-status-id="${escapeHTML(app.id)}"
                                aria-label="Change application status">
                                ${["Pending", "Approved", "Rejected"].map(status => `
                                    <option
                                        value="${status}"
                                        ${status.toLowerCase() ===
                                            String(app.status).toLowerCase()
                                            ? "selected" : ""}>
                                        ${status}
                                    </option>
                                `).join("")}
                            </select>
                        </td>
                    </tr>
                `).join("")
                : `<tr><td colspan="4">No applications yet.</td></tr>`;
        }
    }

    // ---------- FOUND CAT ----------

    function renderFoundReports() {
        const table = document.getElementById("foundTable");

        if (!table) {
            console.error("foundTable was not found in admin.php.");
            return;
        }

        const query = (
            document.getElementById("foundSearch")?.value || ""
        ).toLowerCase();

        const filtered = foundReports.filter(report =>
            `${report.id} ${report.student} ${report.location}`
                .toLowerCase()
                .includes(query)
        );

        if (filtered.length === 0) {
            table.innerHTML = `
                <tr>
                    <td colspan="6">No found cat reports available.</td>
                </tr>
            `;
            return;
        }

        table.innerHTML = filtered.map(report => {
            const rawStatus = String(report.status || "Pending").toLowerCase();

            const currentStatus = [
                "pending", "resolved", "rejected"
            ].includes(rawStatus)
                ? rawStatus
                : "pending";

            const statusLabel =
                currentStatus.charAt(0).toUpperCase() +
                currentStatus.slice(1);

            return `
                <tr>
                    <td>${escapeHTML(report.id)}</td>
                    <td>${escapeHTML(report.student || "-")}</td>
                    <td>${escapeHTML(report.location || "-")}</td>
                    <td>${escapeHTML(report.date || "-")}</td>
                    <td>
                        <span class="badge ${currentStatus}">
                            ${escapeHTML(statusLabel)}
                        </span>
                    </td>
                    <td>
                        <div class="action-group found-actions">
                            <button
                                type="button"
                                class="action-btn found-status-btn pending"
                                data-found-update-id="${escapeHTML(report.id)}"
                                data-new-status="Pending"
                                ${currentStatus === "pending" ? "disabled" : ""}>
                                Pending
                            </button>

                            <button
                                type="button"
                                class="action-btn found-status-btn approve"
                                data-found-update-id="${escapeHTML(report.id)}"
                                data-new-status="Resolved"
                                ${currentStatus === "resolved" ? "disabled" : ""}>
                                Resolved
                            </button>

                            <button
                                type="button"
                                class="action-btn found-status-btn reject"
                                data-found-update-id="${escapeHTML(report.id)}"
                                data-new-status="Rejected"
                                ${currentStatus === "rejected" ? "disabled" : ""}>
                                Rejected
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join("");
    }

    function renderStudents() {
        const table = document.getElementById("studentsTable");
        if (!table) return;

        const query = (
            document.getElementById("studentSearch")?.value || ""
        ).toLowerCase();

        const filtered = students.filter(student =>
            (
                Number(student.adopt_count) > 0 ||
                Number(student.found_count) > 0
            ) &&
            `${student.id} ${student.name} ${student.email}`
                .toLowerCase()
                .includes(query)
        );

        table.innerHTML = filtered.length
            ? filtered.map(student => `
                <tr>
                    <td>${escapeHTML(student.id)}</td>
                    <td><strong>${escapeHTML(student.name)}</strong></td>
                    <td>${escapeHTML(student.email)}</td>
                    <td>${badge(student.status)}</td>
                    <td>${Number(student.adopt_count) || 0}</td>
                    <td>${Number(student.found_count) || 0}</td>
                </tr>
            `).join("")
            : `<tr><td colspan="6">No students have adopted or reported a found cat yet.</td></tr>`;
    }

    function renderReports() {
        const approved = countBy(applications, "Approved");
        const pending = countBy(applications, "Pending");
        const rejected = countBy(applications, "Rejected");
        const total = approved + pending + rejected;

        setText("repTotalCats", cats.length);
        setText("repApproved", approved);
        setText("repPending", pending);
        setText("repFound", foundReports.length);

        setText("legApproved", approved);
        setText("legPending", pending);
        setText("legRejected", rejected);
        setText("pieTotal", total);

        const pie = document.getElementById("adoptionPie");

        if (pie) {
            const pct = number => total ? (number / total) * 100 : 0;
            const approvedPct = pct(approved);
            const pendingPct = approvedPct + pct(pending);

            pie.style.background = total
                ? `conic-gradient(#198754 0 ${approvedPct}%, #e6a11a ${approvedPct}% ${pendingPct}%, #ff2796 ${pendingPct}% 100%)`
                : "#f0e6ec";
        }

        const max = Math.max(1, approved, pending, rejected);

        [["Approved", approved], ["Pending", pending], ["Rejected", rejected]]
            .forEach(([name, number]) => {
                setText(`bar${name}N`, number);

                const bar = document.getElementById(`bar${name}`);

                if (bar) {
                    bar.style.width = `${(number / max) * 100}%`;
                }
            });
    }

    // ---------- STUDENT REWARDS ----------
    // Status:
    //   "-"        : belum cukup laporan
    //   Eligible   : sudah cukup laporan, admin boleh tekan Mark Given
    //   Given      : hadiah sudah diberi (student.reward_given_at ada nilai)

    function renderRewards() {
        const table = document.getElementById("rewardsTable");
        if (!table) return;

        const rankedStudents = students
            .filter(student => Number(student.found_count) > 0)
            .sort((a, b) =>
                Number(b.found_count) - Number(a.found_count)
            )
            .slice(0, 10);

        if (rankedStudents.length === 0) {
            table.innerHTML = `
                <tr>
                    <td colspan="7">
                        No found cat reports from students yet.
                    </td>
                </tr>
            `;
            return;
        }

        table.innerHTML = rankedStudents.map((student, index) => {
            const eligible =
                Number(student.found_count) >= REWARD_MIN_FOUND;
            const given = Boolean(student.reward_given_at);

            let statusCell = "-";
            let actionCell = "-";

            if (given) {
                statusCell = `<span class="badge approved">Given</span>`;
                actionCell = "Given ✓";
            } else if (eligible) {
                statusCell = `<span class="badge pending">Eligible</span>`;
                actionCell = `
                    <button type="button"
                        class="action-btn approve"
                        data-reward-given="${escapeHTML(student.id)}">
                        Mark Given
                    </button>`;
            }

            return `
                <tr>
                    <td>${index === 0 && eligible ? "🏆 " : ""}#${index + 1}</td>
                    <td>${escapeHTML(student.id)}</td>
                    <td><strong>${escapeHTML(student.name)}</strong></td>
                    <td>${escapeHTML(student.email)}</td>
                    <td>${Number(student.found_count)}</td>
                    <td>${statusCell}</td>
                    <td>${actionCell}</td>
                </tr>
            `;
        }).join("");
    }


    // ---------- MANAGE CATS: STATUS BADGE + DROPDOWN STYLE ----------
    // Scoped to #catsTable only. Uses the same colours as admin.css.
    const catStatusStyle = document.createElement("style");
    catStatusStyle.textContent = `
        #catsTable .badge {
            gap: 6px;
            padding: 6px 12px;
            border: 1px solid transparent;
            border-radius: 999px;
            font-size: 10px;
            letter-spacing: .2px;
        }
        #catsTable .badge::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }
        #catsTable .badge.available {
            border-color: #c9ecd9;
        }
        #catsTable .badge.pending {
            border-color: #f6dfb6;
        }
        #catsTable .badge.adopted {
            border-color: #f6cfdb;
        }

        #catsTable .status-select {
            -webkit-appearance: none;
            appearance: none;
            min-width: 118px;
            padding: 7px 30px 7px 13px;
            border: 1px solid #eadfe9;
            border-radius: 999px;
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='7' viewBox='0 0 12 8'%3E%3Cpath d='M1 1.5l5 5 5-5' fill='none' stroke='%23d91e7c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            color: #76647e;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: border-color .2s, box-shadow .2s, transform .2s;
        }
        #catsTable .status-select:hover {
            border-color: var(--pink);
            transform: translateY(-1px);
        }
        #catsTable .status-select:focus {
            border-color: var(--pink);
            box-shadow: 0 0 0 3px rgba(255,39,150,.15);
        }
        #catsTable .status-select[data-status="available"] {
            border-color: #c9ecd9;
            background-color: #f0fbf5;
            color: #198754;
        }
        #catsTable .status-select[data-status="pending"] {
            border-color: #f6dfb6;
            background-color: #fff8ea;
            color: #ad7119;
        }
        #catsTable .status-select[data-status="adopted"] {
            border-color: #f6cfdb;
            background-color: #fff5f8;
            color: #c44a70;
        }
        #catsTable .status-select option {
            background: #fff;
            color: #302838;
        }
    `;
    document.head.appendChild(catStatusStyle);

    // ---------- REVIEW ADOPTION DETAILS MODAL ----------

    const reviewStyle = document.createElement("style");
    reviewStyle.textContent = `
        .review-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15,10,18,.62);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            z-index: 9998;
        }
        .review-modal-overlay.open {
            display: flex;
        }
        .review-modal {
            background: #fff;
            color: #30232b;
            width: min(600px, 100%);
            max-height: 85vh;
            overflow-y: auto;
            padding: 25px;
            border-radius: 18px;
            box-shadow: 0 20px 70px #0003;
        }
        .review-modal h2 {
            margin-top: 0;
        }
        .review-detail-row {
            padding: 12px 0;
            border-bottom: 1px solid #f0e4eb;
            overflow-wrap: anywhere;
        }
        .review-detail-label {
            display: block;
            color: #9d4774;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .review-close {
            margin-top: 20px;
            padding: 10px 18px;
            border: 0;
            border-radius: 9px;
            background: #ff2796;
            color: white;
            cursor: pointer;
        }
        .view-details-btn {
            background: #7c3aed !important;
            color: white !important;
        }
    `;
    document.head.appendChild(reviewStyle);

    const reviewOverlay = document.createElement("div");
    reviewOverlay.className = "review-modal-overlay";
    reviewOverlay.id = "reviewModalOverlay";

    reviewOverlay.innerHTML = `
        <section class="review-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reviewModalTitle">

            <h2 id="reviewModalTitle">Adoption Application Details</h2>
            <div id="reviewDetailsContent"></div>

            <button type="button"
                class="review-close"
                id="reviewCloseBtn">
                Close
            </button>
        </section>
    `;

    document.body.appendChild(reviewOverlay);

    function openReviewModal(id) {
        const app = applications.find(
            item => String(item.id) === String(id)
        );

        if (!app) {
            showToast("Application not found. Please refresh.");
            return;
        }

        const details = [
            ["Application ID", app.id],
            ["Student Name", app.student],
            ["Student Email", app.email],
            ["Student ID", app.student_id],
            ["Cat Name", app.cat],
            ["Cat Age", app.cat_age],
            ["Cat Gender", app.cat_gender],
            ["Application Date", app.date],
            ["Reason for Adoption", app.reason],
            ["Application Status", app.status]
        ];

        // Any other fields the student submitted (phone, address, housing, etc.)
        Object.entries(app.extra || {}).forEach(([key, value]) => {
            const label = key
                .replace(/_/g, " ")
                .replace(/\b\w/g, letter => letter.toUpperCase());
            details.push([label, value]);
        });

        // Pickup details (only once the admin has saved them)
        if (app.pickup_date && app.pickup_time) {
            details.push(
                ["Pickup Location", app.pickup_location],
                ["Pickup Date", app.pickup_date],
                ["Pickup Time", String(app.pickup_time).slice(0, 5)],
                ["Admin Contact", app.admin_contact],
                ["Pickup Notes", app.pickup_notes]
            );
        }

        if (app.picked_up_at) {
            details.push(["Picked Up At", app.picked_up_at]);
        }

        document.getElementById("reviewDetailsContent").innerHTML =
            details.map(([label, value]) => `
                <div class="review-detail-row">
                    <span class="review-detail-label">
                        ${escapeHTML(label)}
                    </span>
                    <div>${escapeHTML(value || "Not provided")}</div>
                </div>
            `).join("");

        reviewOverlay.classList.add("open");
    }

    function closeReviewModal() {
        reviewOverlay.classList.remove("open");
    }

    document.getElementById("reviewCloseBtn")
        .addEventListener("click", closeReviewModal);

    reviewOverlay.addEventListener("click", event => {
        if (event.target === reviewOverlay) {
            closeReviewModal();
        }
    });

    // ---------- PICKUP DETAILS MODAL ----------
    const pickupStyle = document.createElement('style');
    pickupStyle.textContent = `
        .pickup-modal-overlay{position:fixed;inset:0;background:rgba(15,10,18,.62);display:none;align-items:center;justify-content:center;padding:18px;z-index:9999}
        .pickup-modal-overlay.open{display:flex}
        .pickup-modal{background:#fff;width:min(560px,100%);max-height:90vh;overflow:auto;border-radius:18px;padding:24px;box-shadow:0 20px 70px #0003;color:#24202a}
        .pickup-modal h2{margin:0 0 6px;font-size:22px}.pickup-modal p{margin:0 0 18px;color:#766b78;font-size:13px}
        .pickup-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:13px}.pickup-form-field{display:flex;flex-direction:column;gap:6px;min-width:0}
        .pickup-form-field.full{grid-column:1/-1}.pickup-form-field label{font-size:12px;font-weight:700;color:#544653}
        .pickup-form-field input,.pickup-form-field textarea{width:100%;box-sizing:border-box;border:1px solid #e5d6e1;border-radius:9px;padding:11px;font:inherit;background:#fff;color:#29222a}
        .pickup-form-field textarea{min-height:80px;resize:vertical}.pickup-modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
        .pickup-modal-actions button{border:0;border-radius:9px;padding:10px 16px;cursor:pointer;font-weight:700}.pickup-cancel{background:#f3eaf0;color:#574452}.pickup-save{background:#ff2796;color:white}
        .pickup-edit-btn{background:#7c3aed!important;color:white!important}.pickup-edit-btn:disabled{opacity:.6}
        @media(max-width:520px){.pickup-form-grid{grid-template-columns:1fr}.pickup-form-field.full{grid-column:auto}.pickup-modal{padding:18px}}
    `;
    document.head.appendChild(pickupStyle);

    const pickupOverlay = document.createElement('div');
    pickupOverlay.className = 'pickup-modal-overlay';
    pickupOverlay.id = 'pickupModalOverlay';
    pickupOverlay.innerHTML = `
        <section class="pickup-modal" role="dialog" aria-modal="true" aria-labelledby="pickupModalTitle">
            <h2 id="pickupModalTitle">Set Pickup Details</h2>
            <p>Only available for approved adoption applications. Saved details will appear in the student's My Application page.</p>
            <form id="pickupDetailsForm">
                <input type="hidden" id="pickupApplicationId">
                <div class="pickup-form-grid">
                    <div class="pickup-form-field full"><label for="pickupLocationInput">Pickup Location *</label><input id="pickupLocationInput" required value="College Management Office" maxlength="255"></div>
                    <div class="pickup-form-field"><label for="pickupDateInput">Pickup Date *</label><input id="pickupDateInput" type="date" required></div>
                    <div class="pickup-form-field"><label for="pickupTimeInput">Pickup Time *</label><input id="pickupTimeInput" type="time" required></div>
                    <div class="pickup-form-field full"><label for="pickupContactInput">Admin Contact</label><input id="pickupContactInput" maxlength="255" placeholder="Phone number or email"></div>
                    <div class="pickup-form-field full"><label for="pickupNotesInput">Pickup Notes</label><textarea id="pickupNotesInput" maxlength="2000" placeholder="Instructions for the student"></textarea></div>
                </div>
                <div class="pickup-modal-actions"><button type="button" class="pickup-cancel" id="pickupCancelBtn">Cancel</button><button type="submit" class="pickup-save" id="pickupSaveBtn">Save Pickup Details</button></div>
            </form>
        </section>`;
    document.body.appendChild(pickupOverlay);

    function openPickupModal(id) {
        const app = applications.find(item => String(item.id) === String(id));
        if (!app) { showToast('Application not found. Refresh the dashboard and try again.'); return; }
        document.getElementById('pickupApplicationId').value = app.id;
        document.getElementById('pickupLocationInput').value = app.pickup_location || 'College Management Office';
        document.getElementById('pickupDateInput').value = app.pickup_date || '';
        document.getElementById('pickupTimeInput').value = String(app.pickup_time || '').slice(0, 5);
        document.getElementById('pickupContactInput').value = app.admin_contact || '';
        document.getElementById('pickupNotesInput').value = app.pickup_notes || '';
        document.getElementById('pickupModalTitle').textContent = app.pickup_date && app.pickup_time ? 'Edit Pickup Details' : 'Set Pickup Details';
        pickupOverlay.classList.add('open');
    }
    function closePickupModal() { pickupOverlay.classList.remove('open'); }
    document.getElementById('pickupCancelBtn').addEventListener('click', closePickupModal);
    pickupOverlay.addEventListener('click', event => { if (event.target === pickupOverlay) closePickupModal(); });

    document.getElementById('pickupDetailsForm').addEventListener('submit', async event => {
        event.preventDefault();
        const saveButton = document.getElementById('pickupSaveBtn');
        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';
        const success = await send({
            action: 'save_pickup_details',
            application_id: document.getElementById('pickupApplicationId').value,
            pickup_location: document.getElementById('pickupLocationInput').value.trim(),
            pickup_date: document.getElementById('pickupDateInput').value,
            pickup_time: document.getElementById('pickupTimeInput').value,
            admin_contact: document.getElementById('pickupContactInput').value.trim(),
            pickup_notes: document.getElementById('pickupNotesInput').value.trim()
        }, 'Pickup details saved.');
        saveButton.disabled = false;
        saveButton.textContent = 'Save Pickup Details';
        if (success) closePickupModal();
    });

    function renderAll() {
        renderAdmin();
        renderOverview();
        renderCats();
        renderApplications();
        renderFoundReports();
        renderStudents();
        renderReports();
        renderRewards();
    }

    // ---------- SEARCH AND FILTERS ----------

    document.getElementById("catSearch")
        ?.addEventListener("input", renderCats);

    document.getElementById("catStatusFilter")
        ?.addEventListener("change", renderCats);

    document.getElementById("applicationSearch")
        ?.addEventListener("input", renderApplications);

    document.getElementById("applicationStatusFilter")
        ?.addEventListener("change", renderApplications);

    document.getElementById("foundSearch")
        ?.addEventListener("input", renderFoundReports);

    document.getElementById("studentSearch")
        ?.addEventListener("input", renderStudents);

    // ---------- ADD CAT MODAL ----------

    const modal = document.getElementById("catModal");

    function openModal() {
        if (!modal) return;

        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
        document.getElementById("newCatName")?.focus();
    }

    function closeModal() {
        if (!modal) return;

        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
    }

    document.getElementById("addCatBtn")
        ?.addEventListener("click", openModal);

    document.getElementById("closeCatModal")
        ?.addEventListener("click", closeModal);

    document.getElementById("cancelCatBtn")
        ?.addEventListener("click", closeModal);

    modal?.addEventListener("click", event => {
        if (event.target === modal) closeModal();
    });

    document.getElementById("catForm")
        ?.addEventListener("submit", async event => {
            event.preventDefault();

            const name =
                document.getElementById("newCatName")?.value.trim() || "";
            const age =
                document.getElementById("newCatAge")?.value.trim() || "";
            const gender =
                document.getElementById("newCatGender")?.value || "";
            const status =
                document.getElementById("newCatStatus")?.value || "Available";

            if (!name || !age) {
                showToast("Please complete all required fields.");
                return;
            }

            const success = await send(
                { action: "add_cat", name, age, gender, status },
                `${name} was added.`
            );

            if (success) {
                event.target.reset();
                closeModal();
            }
        });

    // ---------- ACTION BUTTONS ----------

    document.addEventListener("click", async event => {
        const target = event.target;

        const viewButton = target.closest("[data-view-application]");

        if (viewButton) {
            openReviewModal(viewButton.dataset.viewApplication);
            return;
        }

        const pickupButton = target.closest("[data-pickup-edit]");
        if (pickupButton) {
            openPickupModal(pickupButton.dataset.pickupEdit);
            return;
        }

        const pickedButton = target.closest("[data-mark-picked]");
        if (pickedButton) {
            if (pickedButton.disabled) return;

            if (!confirm("Confirm that the cat has been handed over to the adopter?")) {
                return;
            }

            pickedButton.disabled = true;

            const success = await send({
                action: "mark_picked_up",
                application_id: pickedButton.dataset.markPicked
            }, "Application marked as picked up.");

            if (!success) pickedButton.disabled = false;
            return;
        }

        // ----- STUDENT REWARD: MARK GIVEN -----
        const rewardButton = target.closest("[data-reward-given]");
        if (rewardButton) {
            if (rewardButton.disabled) return;

            if (!confirm("Mark this reward as given to the student?")) {
                return;
            }

            rewardButton.disabled = true;

            const success = await send({
                action: "mark_reward_given",
                student_id: rewardButton.dataset.rewardGiven
            }, "Reward marked as given.");

            if (!success) rewardButton.disabled = false;
            return;
        }

        const appButton = target.closest("[data-app-action]");

        if (appButton) {
            if (appButton.disabled) return;

            const status = appButton.dataset.appAction;
            const id = appButton.dataset.appId;

            if (!id) {
                showToast("Application ID is missing.");
                return;
            }

            appButton.disabled = true;

            const success = await send({
                action: "update_application",
                application_id: id,
                status
            }, `Application marked as ${status}.`);

            if (!success) appButton.disabled = false;
            return;
        }

        const foundButton = target.closest("[data-found-update-id]");

        if (foundButton) {
            if (foundButton.disabled) return;

            const id = foundButton.dataset.foundUpdateId;
            const status = foundButton.dataset.newStatus;

            if (!id || !status) {
                showToast("Found Cat report ID or status is missing.");
                return;
            }

            if (!["Pending", "Resolved", "Rejected"].includes(status)) {
                showToast("Invalid Found Cat status.");
                return;
            }

            if (status === "Resolved") {
                const confirmed = window.confirm(
                    "Mark this report as Resolved? Check your database logic to confirm whether this also adds the cat to Adopt a Cat."
                );

                if (!confirmed) return;
            }

            const originalText = foundButton.textContent;

            foundButton.disabled = true;
            foundButton.textContent = "Updating...";

            try {
                const result = await api({
                    action: "update_found",
                    found_id: id,
                    status
                });

                if (!result || result.success !== true) {
                    throw new Error(
                        result?.message || "Unable to update Found Cat status."
                    );
                }

                showToast(
                    result.message || `Report status updated to ${status}.`
                );

                await loadData();
            } catch (error) {
                console.error("Found Cat update error:", error);
                showToast(error.message || "Failed to update Found Cat status.");

                foundButton.disabled = false;
                foundButton.textContent = originalText;
            }

            return;
        }

        if (target.closest("#refreshBtn")) {
            await loadData();
            showToast("Dashboard refreshed.");
            return;
        }

        if (target.closest("#printReportBtn")) {
            renderAll();

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    window.print();
                });
            });

            return;
        }

        if (target.closest("#logoutBtn")) {
            if (window.confirm("Are you sure you want to log out?")) {
                window.location.href = LOGOUT_URL;
            }
        }
    });

    // ---------- CHANGE EVENTS ----------

    document.addEventListener("change", async event => {
        const target = event.target;

        const appSelect = target.closest("[data-app-status-id]");

        if (appSelect) {
            const previousValue = appSelect.dataset.previousValue;

            appSelect.dataset.previousValue = appSelect.value;
            appSelect.disabled = true;

            const ok = await send({
                action: "update_application",
                application_id: appSelect.dataset.appStatusId,
                status: appSelect.value
            }, `Application marked as ${appSelect.value}.`);

            appSelect.disabled = false;

            if (!ok) {
                if (previousValue) appSelect.value = previousValue;
                else renderApplications();
            }

            return;
        }

        const catSelect = target.closest("[data-cat-id]");
        if (!catSelect) return;

        catSelect.disabled = true;

        const ok = await send({
            action: "update_cat",
            cat_id: catSelect.dataset.catId,
            status: catSelect.value
        }, "Cat status updated.");

        catSelect.disabled = false;

        if (!ok) renderCats();
    });

    // ---------- INITIALISE ----------

    const requestedPage = window.location.hash.replace("#", "");

    setPage(
        Object.prototype.hasOwnProperty.call(pageNames, requestedPage)
            ? requestedPage
            : "dashboard"
    );

    loadData();
});