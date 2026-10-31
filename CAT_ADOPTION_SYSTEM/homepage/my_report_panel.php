<?php
/*
 * Panel tab "My Reports": progress reward + pilih reward + butang claim WhatsApp + senarai laporan.
 * Di-include dari found_cat.php (session dan $pdo sudah wujud di sana).
 */
if (!function_exists('mr_h')) {
    function mr_h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
    function mr_find(array $cols, array $cands): ?string
    {
        $lower = array_map('strtolower', $cols);
        foreach ($cands as $c) {
            $i = array_search($c, $lower, true);
            if ($i !== false) {
                return $cols[$i];
            }
        }
        return null;
    }
    function mr_status(array $row, ?string $col): string
    {
        $s = $col ? strtolower(trim((string) ($row[$col] ?? ''))) : '';
        $s = preg_replace('/[^a-z]/', '', $s);
        return $s === '' ? 'pending' : $s;
    }
}

/* ===== TETAPAN ===== */
$mrTable        = 'found_cats';
$mrStudentTable = 'login_student';
$mrValid        = ['reviewed', 'resolved', 'verified', 'approved']; // status yang dikira laporan sah
$mrGoal         = 4;                                                // bilangan laporan untuk claim
$mrWhatsApp     = '601119790155';                                   // 011 1979 0155
/* =================== */

$mrPdo = $pdo ?? $conn ?? null;
$mrSid = (int) ($_SESSION['student_id'] ?? 0);
$mrRows = [];
$mrError = '';
$cStatus = $cDate = $cName = $cText = $cLoc = $cImg = $cId = null;

if (!$mrPdo instanceof PDO || $mrSid === 0) {
    $mrError = 'Please log in as a student to view your reports.';
} else {
    try {
        $cols    = $mrPdo->query("SHOW COLUMNS FROM `$mrTable`")->fetchAll(PDO::FETCH_COLUMN);
        $cStud   = mr_find($cols, ['student_id', 'user_id', 'reporter_id']);
        $cStatus = mr_find($cols, ['status', 'report_status']);
        $cDate   = mr_find($cols, ['submitted_at', 'created_at', 'reported_at', 'report_date']);
        $cName   = mr_find($cols, ['cat_name', 'name']);
        $cText   = mr_find($cols, ['description', 'details']);
        $cLoc    = mr_find($cols, ['location', 'found_location', 'place']);
        $cImg    = mr_find($cols, ['image_path', 'image', 'photo', 'photo_path']);
        $cId     = mr_find($cols, ['id', 'report_id', 'found_id']) ?? $cols[0];
        if ($cStud === null) {
            throw new RuntimeException('No student column in ' . $mrTable);
        }
        $st = $mrPdo->prepare("SELECT * FROM `$mrTable` WHERE `$cStud` = ? ORDER BY `" . ($cDate ?? $cId) . "` DESC, `$cId` DESC");
        $st->execute([$mrSid]);
        $mrRows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $mrError = 'Unable to load your reports. Please check the found_cats table.';
    }
}

/* Nama pelajar untuk mesej WhatsApp */
$mrName = trim((string) ($_SESSION['student_name'] ?? ''));
if ($mrName === '' && $mrPdo instanceof PDO && $mrSid > 0) {
    try {
        $sc = $mrPdo->query("SHOW COLUMNS FROM `$mrStudentTable`")->fetchAll(PDO::FETCH_COLUMN);
        $ic = mr_find($sc, ['student_id', 'id', 'user_id']);
        $nc = mr_find($sc, ['name', 'full_name', 'student_name', 'fullname', 'username']);
        if ($ic && $nc) {
            $q = $mrPdo->prepare("SELECT `$nc` FROM `$mrStudentTable` WHERE `$ic` = ?");
            $q->execute([$mrSid]);
            $mrName = (string) $q->fetchColumn();
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }
}
if ($mrName === '') {
    $mrName = 'Student';
}

$mrDone = $mrPending = $mrRejected = 0;
foreach ($mrRows as $r) {
    $s = mr_status($r, $cStatus);
    if (in_array($s, $mrValid, true))  $mrDone++;
    elseif ($s === 'rejected')         $mrRejected++;
    else                               $mrPending++;
}
$mrPct      = min(100, (int) round($mrDone / max(1, $mrGoal) * 100));
$mrRemain   = max(0, $mrGoal - $mrDone);
$mrEligible = $mrDone >= $mrGoal;

/* Semak sama ada admin sudah menanda hadiah sebagai diberi (jadual reward_given). */
$mrGivenAt = null;
if ($mrPdo instanceof PDO && $mrSid > 0) {
    try {
        $gs = $mrPdo->prepare('SELECT given_at FROM `reward_given` WHERE student_id = ? LIMIT 1');
        $gs->execute([$mrSid]);
        $mrGivenAt = $gs->fetchColumn() ?: null;
    } catch (Throwable $e) {
        // Jadual belum wujud: anggap hadiah belum diberi.
        $mrGivenAt = null;
    }
}
$mrGiven = $mrGivenAt !== null;
$mrGivenTs = $mrGiven ? strtotime((string) $mrGivenAt) : false;

/* Mesej asas. Reward yang dipilih dan ruang bank/alamat ditambah oleh JavaScript. */
$mrMsg  = "Hi admin, I am $mrName (Student ID: $mrSid). I have completed $mrGoal found cat reports and would like to claim my reward. Here are my details:";
$mrLink = 'https://wa.me/' . $mrWhatsApp . '?text=' . rawurlencode($mrMsg);

/* Senarai reward yang boleh dipilih */
$mrRewards = [
    ['value' => 'Cash RM50',        'title' => 'Cash RM50',        'note' => 'Sent to your bank account',
     'img' => 'cash_50.jpg',
     'pos' => 'center'],
    ['value' => 'Vet Coupon RM100', 'title' => 'Vet Coupon RM100', 'note' => 'Animal clinic coupon',
     'img' => 'klinik.webp',
     'pos' => 'center 30%'],
    ['value' => 'Cat Care Items',   'title' => 'Cat Care Items',   'note' => 'Essentials for cats',
     'img' => 'cat_care.webp',
     'pos' => 'center 55%'],
];
?>
<style>
.mr{--a:#cf5688;--ad:#b63d70;--soft:#fff0f6;--line:#f1d9e4;--mut:#8e7b86}
.mr *{box-sizing:border-box}
.mr-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:24px;margin-bottom:20px;box-shadow:0 10px 35px rgba(109,58,82,.045)}
.mr-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.mr-head h3{margin:0;font-size:18px}
.mr-head span{font-size:12px;color:var(--mut)}
.mr-bar{height:11px;border-radius:9px;background:#fbe3ec;overflow:hidden}
.mr-bar i{display:block;height:100%;border-radius:9px;background:linear-gradient(90deg,#e686ab,var(--ad))}
.mr-bar.ok i{background:linear-gradient(90deg,#34c27a,#159653)}
.mr-steps{display:grid;grid-template-columns:repeat(4,1fr);text-align:center;font-size:12px;color:var(--mut);margin:14px 0}
.mr-steps b{display:block;color:var(--text,#45333d)}
.mr-steps .on,.mr-steps .on b{color:var(--ad)}
.mr-note{background:var(--soft);border-radius:11px;padding:13px 16px;font-size:12px;line-height:1.7}
.mr-note.done{background:#eaf6ee;color:#27663d}

.mr-rewards{margin-top:18px}
.mr-rewards h4{margin:0 0 12px;font-size:13px;color:var(--ad)}
.mr-rlist{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.mr-ritem{display:flex;flex-direction:column;justify-content:flex-end;position:relative;overflow:hidden;min-height:170px;cursor:pointer;transition:.15s;background-color:#c46a8d;background-size:cover;background-position:center;border:2px solid transparent;border-radius:14px;padding:14px;text-align:left;color:#fff}
.mr-ritem::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(40,15,30,.05) 25%,rgba(40,15,30,.8))}
.mr-ritem input{position:absolute;opacity:0;pointer-events:none}
.mr-ritem b,.mr-ritem span{position:relative}
.mr-ritem b{display:block;font-size:14px;color:#fff}
.mr-ritem span{display:block;margin-top:3px;font-size:11px;color:rgba(255,255,255,.88)}
.mr-ritem:hover{border-color:var(--a)}
.mr-ritem:has(input:checked){border-color:var(--a);box-shadow:0 0 0 3px rgba(207,86,136,.35)}
.mr-ritem:has(input:checked)::after{content:"✓";position:absolute;top:10px;right:10px;width:26px;height:26px;display:grid;place-items:center;border-radius:50%;background:var(--a);color:#fff;font-size:15px;font-weight:700}
.mr-ritem:has(input:focus-visible){outline:2px solid var(--a);outline-offset:2px}
.mr-ritem:has(input:disabled){cursor:not-allowed;opacity:.6}
.mr-err{margin:10px 0 0;font-size:12px;color:#9b2c2c}
.mr-err[hidden]{display:none}

.mr-claim{margin-top:16px;width:100%;min-height:48px;border:0;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;color:#fff;background:var(--a)}
.mr-claim:hover{background:var(--ad)}
.mr-claim:disabled{background:#eadfe5;color:#a39099;cursor:not-allowed}
.mr-wrap{overflow-x:auto}
.mr table{width:100%;min-width:620px;border-collapse:collapse}
.mr th{text-align:left;font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:#8a6077;background:var(--soft);padding:12px 14px}
.mr td{padding:14px;border-top:1px solid #f5e3eb;font-size:12px;vertical-align:middle}
.mr-photo{width:54px;height:54px;border-radius:11px;object-fit:cover;border:1px solid var(--line);background:var(--soft);display:grid;place-items:center;font-size:24px}
.mr-tag{display:inline-block;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;background:#efe8ec;color:#6e5d67}
.mr-tag.pending{background:#fdf1d9;color:#8a5a0b}
.mr-tag.reviewed{background:#e3effb;color:#1d4f86}
.mr-tag.resolved,.mr-tag.verified,.mr-tag.approved{background:#e5f4e9;color:#27663d}
.mr-tag.rejected{background:#fdeaea;color:#9b2c2c}
.mr-empty{text-align:center;padding:34px 10px;color:var(--mut);font-size:13px}
.mr-modal{position:fixed;inset:0;background:rgba(40,15,30,.55);display:flex;align-items:center;justify-content:center;padding:20px;z-index:2000}
.mr-modal[hidden]{display:none}
.mr-box{background:#fff;border-radius:20px;max-width:400px;width:100%;padding:30px 26px;text-align:center}
.mr-box .p{font-size:46px}
.mr-box h3{margin:8px 0;font-family:Georgia,serif;font-size:24px;color:var(--ad)}
.mr-box p{margin:0 0 18px;line-height:1.6;font-size:13px;color:var(--mut)}
.mr-wa{display:block;padding:13px;border-radius:10px;background:#25d366;color:#fff;font-weight:700;font-size:13px}
.mr-x{margin-top:10px;background:none;border:0;color:var(--mut);cursor:pointer;font-size:12px}
@media(max-width:600px){
    .mr-steps{font-size:11px}
    .mr-rlist{grid-template-columns:1fr}
}
</style>

<div class="mr">

    <div class="mr-card">
        <div class="mr-head">
            <h3>Student Reward Progress</h3>
            <span>Get <?= $mrGoal ?> reviewed reports to claim your reward</span>
        </div>
        <div class="mr-bar <?= $mrEligible ? 'ok' : '' ?>"><i style="width:<?= $mrPct ?>%"></i></div>
        <div class="mr-steps">
            <?php for ($i = 1; $i <= $mrGoal; $i++): $on = $mrDone >= $i; ?>
                <div class="<?= $on ? 'on' : '' ?>"><b>Report <?= $i ?></b><?= $on ? 'Completed' : 'Not yet' ?></div>
            <?php endfor; ?>
        </div>

        <?php if ($mrGiven): ?>
            <div class="mr-note done">
                🎉 Reward claimed<?= $mrGivenTs ? ' on ' . mr_h(date('d M Y', $mrGivenTs)) : '' ?>.
                Thank you for helping the cats!
            </div>
        <?php else: ?>
            <div class="mr-note">
                <?php if ($mrEligible): ?>
                    🎉 You are eligible! Choose your reward below, then press the button to claim it.
                <?php else: ?>
                    You have <b><?= $mrDone ?>/<?= $mrGoal ?></b> completed reports. Submit <b><?= $mrRemain ?> more</b> found cat report<?= $mrRemain === 1 ? '' : 's' ?> to claim your reward. Only reports reviewed by the admin are counted.
                <?php endif; ?>
            </div>

            <div class="mr-rewards">
                <h4>Choose your reward</h4>
                <div class="mr-rlist">
                    <?php foreach ($mrRewards as $rw): ?>
                        <label class="mr-ritem" style="background-image:url('<?= mr_h($rw['img']) ?>');background-position:<?= mr_h($rw['pos']) ?>">
                            <input type="radio" name="mr_reward" value="<?= mr_h($rw['value']) ?>" <?= $mrEligible ? '' : 'disabled' ?>>
                            <b><?= mr_h($rw['title']) ?></b>
                            <span><?= mr_h($rw['note']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="mr-err" id="mrPickErr" hidden>Choose one reward first.</p>
            </div>

            <?php if ($mrEligible): ?>
                <button type="button" class="mr-claim" id="mrClaimBtn" onclick="mrOpen()">🎁 Claim Your Reward</button>
            <?php else: ?>
                <button type="button" class="mr-claim" disabled>🔒 Reward locked</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="mr-card">
        <div class="mr-head">
            <h3>My Found Cat Reports</h3>
            <span><?= count($mrRows) ?> submitted · <?= $mrPending ?> pending · <?= $mrRejected ?> rejected</span>
        </div>

        <?php if ($mrError !== ''): ?>
            <div class="mr-empty"><?= mr_h($mrError) ?></div>
        <?php elseif (!$mrRows): ?>
            <div class="mr-empty"><div style="font-size:38px">🐾</div>You have not submitted any report yet.</div>
        <?php else: ?>
            <div class="mr-wrap">
            <table>
                <thead>
                    <tr><?php if ($cImg): ?><th>Photo</th><?php endif; ?><th>Details</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($mrRows as $r):
                    $s   = mr_status($r, $cStatus);
                    $img = $cImg ? trim((string) ($r[$cImg] ?? '')) : '';
                    $img = ($img !== '' && basename($img) === $img) ? $img : '';
                    $ts  = $cDate ? strtotime((string) ($r[$cDate] ?? '')) : false;
                    $nm  = $cName ? trim((string) ($r[$cName] ?? '')) : '';
                    if ($nm === '') $nm = 'Unknown'; ?>
                    <tr>
                        <?php if ($cImg): ?>
                        <td>
                            <?php if ($img !== ''): ?>
                                <img class="mr-photo" src="<?= mr_h($img) ?>" alt="Cat" onerror="this.outerHTML='<span class=&quot;mr-photo&quot;>🐱</span>'">
                            <?php else: ?>
                                <span class="mr-photo">🐱</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <strong style="font-size:13px"><?= mr_h($nm) ?></strong>
                            <?php if ($cLoc && !empty($r[$cLoc])): ?>
                                <br><span style="color:var(--mut)">📍 <?= mr_h($r[$cLoc]) ?></span>
                            <?php endif; ?>
                            <?php if ($cText && !empty($r[$cText])): ?>
                                <br><span style="color:#6e5d67"><?= mr_h(mb_strimwidth((string) $r[$cText], 0, 90, '…')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap"><?= $ts ? mr_h(date('d M Y', $ts)) : '-' ?></td>
                        <td><span class="mr-tag <?= mr_h($s) ?>"><?= mr_h(ucfirst($s)) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($mrEligible && !$mrGiven): ?>
    <div id="mrModal" class="mr-modal" hidden>
        <div class="mr-box">
            <div class="p">🎉</div>
            <h3>Congratulations!</h3>
            <p>You can claim your reward here. Please send your details to the admin on WhatsApp.</p>
            <a id="mrWaLink" class="mr-wa" href="<?= mr_h($mrLink) ?>" target="_blank" rel="noopener noreferrer">Open WhatsApp</a>
            <button type="button" class="mr-x" onclick="mrClose()">Close</button>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
var mrBase = <?= json_encode($mrMsg, JSON_UNESCAPED_UNICODE) ?>;
var mrWa   = <?= json_encode($mrWhatsApp) ?>;

function mrOpen() {
    var chosen = document.querySelector('input[name="mr_reward"]:checked');
    var err = document.getElementById('mrPickErr');

    if (!chosen) {
        if (err) err.hidden = false;
        return;
    }
    if (err) err.hidden = true;

    var msg = mrBase +
        "\n\nReward chosen: " + chosen.value +
        "\n\nBank name: \nAccount number: \nOr address: ";

    var link = document.getElementById('mrWaLink');
    if (link) {
        link.href = 'https://wa.me/' + mrWa + '?text=' + encodeURIComponent(msg);
    }

    var m = document.getElementById('mrModal');
    if (m) m.hidden = false;
}

function mrClose() {
    var m = document.getElementById('mrModal');
    if (m) m.hidden = true;
}

document.addEventListener('change', function (e) {
    if (!e.target || e.target.name !== 'mr_reward') return;

    var err = document.getElementById('mrPickErr');
    if (err) err.hidden = true;

    var btn = document.getElementById('mrClaimBtn');
    if (btn) btn.textContent = '🎁 Claim: ' + e.target.value;
});
</script>