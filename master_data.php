<?php
/** master_data.php — ข้อมูลพื้นฐาน HR: ตำแหน่ง / แผนก / ทีม / ประเภทวันลา */
define('SOLARSELL', 1);
require_once __DIR__ . '/app/bootstrap.php';

Auth::requireCan('hr.view');

$canManage = Auth::can('hr.manage');

// ─── POST Handler ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCan('hr.manage');
    csrf_verify();
    $do  = input('do');
    $tab = input('tab') ?: 'positions';
    try {
        switch ($do) {

            // ── ตำแหน่งงาน ──────────────────────────────────
            case 'add_position':
                $n = trim(input('name'));
                if ($n === '') throw new RuntimeException('กรุณากรอกชื่อตำแหน่ง');
                Database::run('INSERT INTO positions (name, sort_order) VALUES (:n,:s)', ['n' => $n, 's' => (int) input('sort_order')]);
                flash('success', "เพิ่มตำแหน่ง \"{$n}\" เรียบร้อย");
                break;
            case 'edit_position':
                $n = trim(input('name'));
                if ($n === '') throw new RuntimeException('กรุณากรอกชื่อตำแหน่ง');
                Database::run('UPDATE positions SET name=:n, sort_order=:s WHERE id=:id', ['n' => $n, 's' => (int) input('sort_order'), 'id' => (int) input('id')]);
                flash('success', 'บันทึกเรียบร้อย');
                break;
            case 'toggle_position':
                Database::run('UPDATE positions SET is_active=1-is_active WHERE id=:id', ['id' => (int) input('id')]);
                break;
            case 'del_position':
                Database::run('DELETE FROM positions WHERE id=:id', ['id' => (int) input('id')]);
                flash('success', 'ลบเรียบร้อย');
                break;

            // ── แผนก / ฝ่าย ─────────────────────────────────
            case 'add_dept':
                $n = trim(input('name'));
                if ($n === '') throw new RuntimeException('กรุณากรอกชื่อแผนก');
                Database::run('INSERT INTO departments (name, sort_order) VALUES (:n,:s)', ['n' => $n, 's' => (int) input('sort_order')]);
                flash('success', "เพิ่มแผนก \"{$n}\" เรียบร้อย");
                break;
            case 'edit_dept':
                $n = trim(input('name'));
                if ($n === '') throw new RuntimeException('กรุณากรอกชื่อแผนก');
                Database::run('UPDATE departments SET name=:n, sort_order=:s WHERE id=:id', ['n' => $n, 's' => (int) input('sort_order'), 'id' => (int) input('id')]);
                flash('success', 'บันทึกเรียบร้อย');
                break;
            case 'toggle_dept':
                Database::run('UPDATE departments SET is_active=1-is_active WHERE id=:id', ['id' => (int) input('id')]);
                break;
            case 'del_dept':
                Database::run('DELETE FROM departments WHERE id=:id', ['id' => (int) input('id')]);
                flash('success', 'ลบเรียบร้อย');
                break;

            // ── ทีมงาน ──────────────────────────────────────
            case 'add_team':
                $code = strtoupper(trim(input('code')));
                $name = trim(input('name'));
                if ($code === '' || $name === '') throw new RuntimeException('กรุณากรอกรหัสและชื่อทีม');
                Database::run('INSERT INTO teams (code, name, sort_order) VALUES (:c,:n,:s)', ['c' => $code, 'n' => $name, 's' => (int) input('sort_order')]);
                flash('success', "เพิ่มทีม {$code} เรียบร้อย");
                break;
            case 'edit_team':
                $name = trim(input('name'));
                if ($name === '') throw new RuntimeException('กรุณากรอกชื่อทีม');
                Database::run('UPDATE teams SET name=:n, sort_order=:s WHERE id=:id', ['n' => $name, 's' => (int) input('sort_order'), 'id' => (int) input('id')]);
                flash('success', 'บันทึกเรียบร้อย');
                break;
            case 'toggle_team':
                Database::run('UPDATE teams SET is_active=1-is_active WHERE id=:id', ['id' => (int) input('id')]);
                break;
            case 'del_team':
                Database::run('DELETE FROM teams WHERE id=:id', ['id' => (int) input('id')]);
                flash('success', 'ลบเรียบร้อย');
                break;

            // ── ประเภทวันลา ──────────────────────────────────
            case 'add_leave_type':
                $code = strtolower(preg_replace('/[^a-z0-9_]/i', '_', trim(input('code'))));
                $name = trim(input('name'));
                if ($code === '' || $name === '') throw new RuntimeException('กรุณากรอกรหัสและชื่อประเภทลา');
                Database::run(
                    'INSERT INTO leave_types (code, name, quota_days, deduct_pay, sort_order) VALUES (:c,:n,:q,:d,:s)',
                    ['c' => $code, 'n' => $name, 'q' => (int) input('quota_days'), 'd' => input('deduct_pay') ? 1 : 0, 's' => (int) input('sort_order')]
                );
                flash('success', "เพิ่มประเภทลา \"{$name}\" เรียบร้อย");
                break;
            case 'edit_leave_type':
                $name = trim(input('name'));
                if ($name === '') throw new RuntimeException('กรุณากรอกชื่อประเภทลา');
                Database::run(
                    'UPDATE leave_types SET name=:n, quota_days=:q, deduct_pay=:d, sort_order=:s WHERE id=:id',
                    ['n' => $name, 'q' => (int) input('quota_days'), 'd' => input('deduct_pay') ? 1 : 0, 's' => (int) input('sort_order'), 'id' => (int) input('id')]
                );
                flash('success', 'บันทึกเรียบร้อย');
                break;
            case 'toggle_leave_type':
                Database::run('UPDATE leave_types SET is_active=1-is_active WHERE id=:id', ['id' => (int) input('id')]);
                break;
            case 'del_leave_type':
                $used = (int) Database::scalar('SELECT COUNT(*) FROM leave_requests WHERE leave_type=:c', ['c' => input('code')]);
                if ($used > 0) throw new RuntimeException("ไม่สามารถลบได้ เนื่องจากมีการใช้งานแล้ว {$used} รายการ");
                Database::run('DELETE FROM leave_types WHERE id=:id', ['id' => (int) input('id')]);
                flash('success', 'ลบเรียบร้อย');
                break;
        }
    } catch (\Throwable $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('master_data.php?tab=' . urlencode($tab));
}

// ─── Load data ──────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'positions';

$dbReady = true;
try {
    $positions   = Database::all('SELECT * FROM positions   ORDER BY sort_order, name');
    $departments = Database::all('SELECT * FROM departments ORDER BY sort_order, name');
    $teams       = Database::all('SELECT * FROM teams       ORDER BY sort_order, code');
    $leaveTypes  = Database::all('SELECT * FROM leave_types ORDER BY sort_order, name');
} catch (\Throwable $e) {
    $dbReady     = false;
    $positions   = $departments = $teams = $leaveTypes = [];
}

$pageTitle = 'ข้อมูลพื้นฐาน';
$activeNav = 'master_data';
require __DIR__ . '/app/layout_header.php';

$tabs = [
    'positions'   => ['ตำแหน่งงาน',  'fa-briefcase',      count($positions)],
    'departments' => ['แผนก / ฝ่าย', 'fa-building',       count($departments)],
    'teams'       => ['ทีมงาน',      'fa-people-group',   count($teams)],
    'leave_types' => ['ประเภทวันลา', 'fa-calendar-xmark', count($leaveTypes)],
];

function statusBadge(int $active): string
{
    return $active
        ? '<span class="badge badge-green">ใช้งาน</span>'
        : '<span class="badge badge-muted">ปิดใช้</span>';
}
?>

<div class="page-header">
  <div>
    <h1><i class="fa-solid fa-sliders" style="color:var(--solar-gold);margin-right:8px;"></i>ข้อมูลพื้นฐาน</h1>
    <p>กำหนดค่าเริ่มต้น ตำแหน่ง · แผนก · ทีม · ประเภทวันลา</p>
  </div>
</div>

<?php if (!$dbReady): ?>
<div class="alert alert-error">
  <i class="fa-solid fa-triangle-exclamation"></i>
  <strong>ยังไม่ได้รันไฟล์ migration</strong> — กรุณารัน:
  <code style="background:rgba(0,0,0,.3);padding:2px 8px;border-radius:4px;margin:0 4px;">
    mysql -u root solarsell &lt; db/phaseD_master_data.sql
  </code>
</div>
<?php endif; ?>

<!-- ─── Tab Navigation ─── -->
<div style="display:flex;gap:0;margin-bottom:24px;border-bottom:2px solid var(--border);">
  <?php foreach ($tabs as $slug => [$label, $icon, $count]): ?>
    <?php $active = ($tab === $slug); ?>
    <a href="?tab=<?= urlencode($slug) ?>"
       style="display:flex;align-items:center;gap:7px;padding:10px 20px;text-decoration:none;
              font-size:14px;font-weight:<?= $active ? '600' : '400' ?>;white-space:nowrap;
              color:<?= $active ? 'var(--solar-gold)' : 'var(--text-muted)' ?>;
              border-bottom:2px solid <?= $active ? 'var(--solar-gold)' : 'transparent' ?>;
              margin-bottom:-2px;transition:color .15s;">
      <i class="fa-solid <?= $icon ?>"></i>
      <?= $label ?>
      <span style="background:<?= $active ? 'var(--solar-gold)' : 'var(--border)' ?>;color:<?= $active ? '#000' : 'var(--text-muted)' ?>;
                   border-radius:99px;padding:1px 7px;font-size:11px;font-weight:700;">
        <?= $count ?>
      </span>
    </a>
  <?php endforeach; ?>
</div>

<?php /* ════════════════════════════════════════════ TAB: ตำแหน่งงาน ═══ */ ?>
<?php if ($tab === 'positions'): ?>

<div class="card">
  <div class="card-head">
    <div class="card-title"><i class="fa-solid fa-briefcase"></i> ตำแหน่งงาน</div>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" style="font-size:13px;" onclick="showAdd('pos-add-row')">
        <i class="fa-solid fa-plus"></i> เพิ่มตำแหน่ง
      </button>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:50px;">#</th>
          <th>ชื่อตำแหน่ง</th>
          <th style="width:80px;text-align:center;">ลำดับ</th>
          <th style="width:100px;text-align:center;">สถานะ</th>
          <?php if ($canManage): ?><th style="width:130px;"></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$positions): ?>
          <tr><td colspan="5" class="text-muted" style="text-align:center;padding:48px 0;">
            <i class="fa-solid fa-briefcase" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px;"></i>
            ยังไม่มีตำแหน่งงาน — <?= $canManage ? 'กดปุ่มเพิ่มเพื่อเริ่มต้น' : 'รอ HR เพิ่มข้อมูล' ?>
          </td></tr>
        <?php else: foreach ($positions as $row): ?>
          <tr>
            <td class="mono text-muted" style="font-size:12px;"><?= (int) $row['id'] ?></td>
            <td style="font-weight:500;"><?= e($row['name']) ?></td>
            <td class="mono text-muted" style="text-align:center;"><?= (int) $row['sort_order'] ?></td>
            <td style="text-align:center;"><?= statusBadge((int) $row['is_active']) ?></td>
            <?php if ($canManage): ?>
            <td style="text-align:right;white-space:nowrap;">
              <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;"
                      onclick="toggleEdit('pos-edit-<?= (int)$row['id'] ?>')">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="toggle_position">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="positions">
                <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;" title="เปิด/ปิด">
                  <i class="fa-solid fa-toggle-<?= $row['is_active'] ? 'on' : 'off' ?>"
                     style="color:<?= $row['is_active'] ? 'var(--green)' : 'var(--text-muted)' ?>"></i>
                </button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('ลบตำแหน่ง\n<?= e(addslashes($row['name'])) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="del_position">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="positions">
                <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;color:var(--red)">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
          <?php if ($canManage): ?>
          <tr id="pos-edit-<?= (int)$row['id'] ?>" class="hidden edit-row">
            <td colspan="5" style="padding:0;">
              <form method="post" style="padding:14px 16px;background:rgba(245,158,11,.05);border-top:1px solid var(--border);">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="edit_position">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="positions">
                <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                  <div class="form-group" style="flex:1;min-width:180px;margin:0;">
                    <label class="form-label">ชื่อตำแหน่ง *</label>
                    <input class="form-input" name="name" value="<?= e($row['name']) ?>" required>
                  </div>
                  <div class="form-group" style="width:90px;margin:0;">
                    <label class="form-label">ลำดับ</label>
                    <input class="form-input" type="number" name="sort_order" value="<?= (int) $row['sort_order'] ?>">
                  </div>
                  <button class="btn btn-primary" style="height:40px;"><i class="fa-solid fa-check"></i> บันทึก</button>
                  <button type="button" class="btn btn-ghost" style="height:40px;"
                          onclick="toggleEdit('pos-edit-<?= (int)$row['id'] ?>')">ยกเลิก</button>
                </div>
              </form>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; endif; ?>

        <?php if ($canManage): ?>
        <tr id="pos-add-row" class="hidden edit-row">
          <td colspan="5" style="padding:0;">
            <form method="post" style="padding:14px 16px;background:rgba(245,158,11,.08);border-top:1px solid var(--border);">
              <?= csrf_field() ?>
              <input type="hidden" name="do" value="add_position">
              <input type="hidden" name="tab" value="positions">
              <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:180px;margin:0;">
                  <label class="form-label" style="color:var(--solar-gold);">ชื่อตำแหน่งใหม่ *</label>
                  <input class="form-input" name="name" placeholder="เช่น ผู้จัดการขาย" required>
                </div>
                <div class="form-group" style="width:90px;margin:0;">
                  <label class="form-label">ลำดับ</label>
                  <input class="form-input" type="number" name="sort_order" value="0">
                </div>
                <button class="btn btn-primary" style="height:40px;"><i class="fa-solid fa-plus"></i> เพิ่ม</button>
                <button type="button" class="btn btn-ghost" style="height:40px;"
                        onclick="toggleEdit('pos-add-row')">ยกเลิก</button>
              </div>
            </form>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php /* ════════════════════════════════════════════ TAB: แผนก ══════════ */ ?>
<?php elseif ($tab === 'departments'): ?>

<div class="card">
  <div class="card-head">
    <div class="card-title"><i class="fa-solid fa-building"></i> แผนก / ฝ่าย</div>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" style="font-size:13px;" onclick="showAdd('dept-add-row')">
        <i class="fa-solid fa-plus"></i> เพิ่มแผนก
      </button>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:50px;">#</th>
          <th>ชื่อแผนก / ฝ่าย</th>
          <th style="width:80px;text-align:center;">ลำดับ</th>
          <th style="width:100px;text-align:center;">สถานะ</th>
          <?php if ($canManage): ?><th style="width:130px;"></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$departments): ?>
          <tr><td colspan="5" class="text-muted" style="text-align:center;padding:48px 0;">
            <i class="fa-solid fa-building" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px;"></i>
            ยังไม่มีแผนก
          </td></tr>
        <?php else: foreach ($departments as $row): ?>
          <tr>
            <td class="mono text-muted" style="font-size:12px;"><?= (int) $row['id'] ?></td>
            <td style="font-weight:500;"><?= e($row['name']) ?></td>
            <td class="mono text-muted" style="text-align:center;"><?= (int) $row['sort_order'] ?></td>
            <td style="text-align:center;"><?= statusBadge((int) $row['is_active']) ?></td>
            <?php if ($canManage): ?>
            <td style="text-align:right;white-space:nowrap;">
              <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;"
                      onclick="toggleEdit('dept-edit-<?= (int)$row['id'] ?>')">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="toggle_dept">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="departments">
                <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;" title="เปิด/ปิด">
                  <i class="fa-solid fa-toggle-<?= $row['is_active'] ? 'on' : 'off' ?>"
                     style="color:<?= $row['is_active'] ? 'var(--green)' : 'var(--text-muted)' ?>"></i>
                </button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('ลบแผนก\n<?= e(addslashes($row['name'])) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="del_dept">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="departments">
                <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;color:var(--red)">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
          <?php if ($canManage): ?>
          <tr id="dept-edit-<?= (int)$row['id'] ?>" class="hidden edit-row">
            <td colspan="5" style="padding:0;">
              <form method="post" style="padding:14px 16px;background:rgba(245,158,11,.05);border-top:1px solid var(--border);">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="edit_dept">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="departments">
                <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                  <div class="form-group" style="flex:1;min-width:180px;margin:0;">
                    <label class="form-label">ชื่อแผนก *</label>
                    <input class="form-input" name="name" value="<?= e($row['name']) ?>" required>
                  </div>
                  <div class="form-group" style="width:90px;margin:0;">
                    <label class="form-label">ลำดับ</label>
                    <input class="form-input" type="number" name="sort_order" value="<?= (int) $row['sort_order'] ?>">
                  </div>
                  <button class="btn btn-primary" style="height:40px;"><i class="fa-solid fa-check"></i> บันทึก</button>
                  <button type="button" class="btn btn-ghost" style="height:40px;"
                          onclick="toggleEdit('dept-edit-<?= (int)$row['id'] ?>')">ยกเลิก</button>
                </div>
              </form>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; endif; ?>

        <?php if ($canManage): ?>
        <tr id="dept-add-row" class="hidden edit-row">
          <td colspan="5" style="padding:0;">
            <form method="post" style="padding:14px 16px;background:rgba(245,158,11,.08);border-top:1px solid var(--border);">
              <?= csrf_field() ?>
              <input type="hidden" name="do" value="add_dept">
              <input type="hidden" name="tab" value="departments">
              <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:180px;margin:0;">
                  <label class="form-label" style="color:var(--solar-gold);">ชื่อแผนกใหม่ *</label>
                  <input class="form-input" name="name" placeholder="เช่น ฝ่ายการตลาด" required>
                </div>
                <div class="form-group" style="width:90px;margin:0;">
                  <label class="form-label">ลำดับ</label>
                  <input class="form-input" type="number" name="sort_order" value="0">
                </div>
                <button class="btn btn-primary" style="height:40px;"><i class="fa-solid fa-plus"></i> เพิ่ม</button>
                <button type="button" class="btn btn-ghost" style="height:40px;"
                        onclick="toggleEdit('dept-add-row')">ยกเลิก</button>
              </div>
            </form>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php /* ════════════════════════════════════════════ TAB: ทีมงาน ════════ */ ?>
<?php elseif ($tab === 'teams'): ?>

<div class="card">
  <div class="card-head">
    <div class="card-title"><i class="fa-solid fa-people-group"></i> ทีมงาน</div>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" style="font-size:13px;" onclick="showAdd('team-add-row')">
        <i class="fa-solid fa-plus"></i> เพิ่มทีม
      </button>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:80px;">รหัสทีม</th>
          <th>ชื่อทีม</th>
          <th style="width:80px;text-align:center;">ลำดับ</th>
          <th style="width:100px;text-align:center;">สถานะ</th>
          <?php if ($canManage): ?><th style="width:130px;"></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$teams): ?>
          <tr><td colspan="5" class="text-muted" style="text-align:center;padding:48px 0;">
            <i class="fa-solid fa-people-group" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px;"></i>
            ยังไม่มีทีมงาน
          </td></tr>
        <?php else: foreach ($teams as $row): ?>
          <tr>
            <td><span class="badge badge-blue"><?= e($row['code']) ?></span></td>
            <td style="font-weight:500;"><?= e($row['name']) ?></td>
            <td class="mono text-muted" style="text-align:center;"><?= (int) $row['sort_order'] ?></td>
            <td style="text-align:center;"><?= statusBadge((int) $row['is_active']) ?></td>
            <?php if ($canManage): ?>
            <td style="text-align:right;white-space:nowrap;">
              <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;"
                      onclick="toggleEdit('team-edit-<?= (int)$row['id'] ?>')">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="toggle_team">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="teams">
                <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;" title="เปิด/ปิด">
                  <i class="fa-solid fa-toggle-<?= $row['is_active'] ? 'on' : 'off' ?>"
                     style="color:<?= $row['is_active'] ? 'var(--green)' : 'var(--text-muted)' ?>"></i>
                </button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('ลบทีม <?= e(addslashes($row['code'])) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="del_team">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="teams">
                <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;color:var(--red)">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
          <?php if ($canManage): ?>
          <tr id="team-edit-<?= (int)$row['id'] ?>" class="hidden edit-row">
            <td colspan="5" style="padding:0;">
              <form method="post" style="padding:14px 16px;background:rgba(245,158,11,.05);border-top:1px solid var(--border);">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="edit_team">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="teams">
                <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                  <div class="form-group" style="width:120px;margin:0;">
                    <label class="form-label">รหัสทีม</label>
                    <input class="form-input mono" value="<?= e($row['code']) ?>" disabled style="opacity:.6;">
                  </div>
                  <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                    <label class="form-label">ชื่อทีม *</label>
                    <input class="form-input" name="name" value="<?= e($row['name']) ?>" required>
                  </div>
                  <div class="form-group" style="width:90px;margin:0;">
                    <label class="form-label">ลำดับ</label>
                    <input class="form-input" type="number" name="sort_order" value="<?= (int) $row['sort_order'] ?>">
                  </div>
                  <button class="btn btn-primary" style="height:40px;"><i class="fa-solid fa-check"></i> บันทึก</button>
                  <button type="button" class="btn btn-ghost" style="height:40px;"
                          onclick="toggleEdit('team-edit-<?= (int)$row['id'] ?>')">ยกเลิก</button>
                </div>
              </form>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; endif; ?>

        <?php if ($canManage): ?>
        <tr id="team-add-row" class="hidden edit-row">
          <td colspan="5" style="padding:0;">
            <form method="post" style="padding:14px 16px;background:rgba(245,158,11,.08);border-top:1px solid var(--border);">
              <?= csrf_field() ?>
              <input type="hidden" name="do" value="add_team">
              <input type="hidden" name="tab" value="teams">
              <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="width:100px;margin:0;">
                  <label class="form-label" style="color:var(--solar-gold);">รหัส *</label>
                  <input class="form-input mono" name="code" placeholder="D, E..." required
                         style="text-transform:uppercase;" maxlength="20">
                </div>
                <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                  <label class="form-label" style="color:var(--solar-gold);">ชื่อทีม *</label>
                  <input class="form-input" name="name" placeholder="เช่น ทีม D" required>
                </div>
                <div class="form-group" style="width:90px;margin:0;">
                  <label class="form-label">ลำดับ</label>
                  <input class="form-input" type="number" name="sort_order" value="0">
                </div>
                <button class="btn btn-primary" style="height:40px;"><i class="fa-solid fa-plus"></i> เพิ่ม</button>
                <button type="button" class="btn btn-ghost" style="height:40px;"
                        onclick="toggleEdit('team-add-row')">ยกเลิก</button>
              </div>
            </form>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php /* ════════════════════════════════════════════ TAB: ประเภทวันลา ══ */ ?>
<?php elseif ($tab === 'leave_types'): ?>

<!-- คำอธิบาย -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:20px;">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;">
    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">โควต้า 0</div>
    <div style="font-size:13px;">= <strong>ไม่จำกัดจำนวนวัน</strong> (เช่น ลาป่วยฉุกเฉิน)</div>
  </div>
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;">
    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">หักเงิน</div>
    <div style="font-size:13px;">วันลาประเภทนี้จะ <strong>หักจากเงินเดือน</strong> เมื่อคิด payroll</div>
  </div>
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;">
    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">รหัส (code)</div>
    <div style="font-size:13px;">ใช้เชื่อมข้อมูลภายใน <strong>ห้ามเปลี่ยนหลังมีการใช้งาน</strong></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div class="card-title"><i class="fa-solid fa-calendar-xmark"></i> ประเภทวันลา</div>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" style="font-size:13px;" onclick="showAdd('lt-add-row')">
        <i class="fa-solid fa-plus"></i> เพิ่มประเภทลา
      </button>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:90px;">รหัส</th>
          <th>ชื่อประเภท</th>
          <th style="width:110px;text-align:center;">โควต้า (วัน/ปี)</th>
          <th style="width:90px;text-align:center;">หักเงิน</th>
          <th style="width:80px;text-align:center;">ลำดับ</th>
          <th style="width:100px;text-align:center;">สถานะ</th>
          <?php if ($canManage): ?><th style="width:130px;"></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$leaveTypes): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;padding:48px 0;">
            <i class="fa-solid fa-calendar-xmark" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px;"></i>
            ยังไม่มีประเภทวันลา
          </td></tr>
        <?php else: foreach ($leaveTypes as $row): ?>
          <tr>
            <td><code style="background:var(--bg-hover);padding:2px 8px;border-radius:4px;font-size:12px;"><?= e($row['code']) ?></code></td>
            <td style="font-weight:500;"><?= e($row['name']) ?></td>
            <td style="text-align:center;">
              <?php if ((int) $row['quota_days'] === 0): ?>
                <span class="badge badge-muted">ไม่จำกัด</span>
              <?php else: ?>
                <span class="badge badge-blue" style="font-weight:700;"><?= (int) $row['quota_days'] ?> วัน</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;">
              <?php if ($row['deduct_pay']): ?>
                <span class="badge badge-red"><i class="fa-solid fa-minus"></i> หักเงิน</span>
              <?php else: ?>
                <span class="badge badge-green"><i class="fa-solid fa-check"></i> ไม่หัก</span>
              <?php endif; ?>
            </td>
            <td class="mono text-muted" style="text-align:center;"><?= (int) $row['sort_order'] ?></td>
            <td style="text-align:center;"><?= statusBadge((int) $row['is_active']) ?></td>
            <?php if ($canManage): ?>
            <td style="text-align:right;white-space:nowrap;">
              <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;"
                      onclick="toggleEdit('lt-edit-<?= (int)$row['id'] ?>')">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="toggle_leave_type">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="leave_types">
                <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;" title="เปิด/ปิด">
                  <i class="fa-solid fa-toggle-<?= $row['is_active'] ? 'on' : 'off' ?>"
                     style="color:<?= $row['is_active'] ? 'var(--green)' : 'var(--text-muted)' ?>"></i>
                </button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('ลบประเภทลา\n<?= e(addslashes($row['name'])) ?>?\n\n(ถ้ามีการใช้งานแล้วจะลบไม่ได้)')">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="del_leave_type">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="code" value="<?= e($row['code']) ?>">
                <input type="hidden" name="tab" value="leave_types">
                <button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;color:var(--red)">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
          <?php if ($canManage): ?>
          <tr id="lt-edit-<?= (int)$row['id'] ?>" class="hidden edit-row">
            <td colspan="7" style="padding:0;">
              <form method="post" style="padding:14px 16px;background:rgba(245,158,11,.05);border-top:1px solid var(--border);">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="edit_leave_type">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="tab" value="leave_types">
                <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                  <div class="form-group" style="width:110px;margin:0;">
                    <label class="form-label">รหัส</label>
                    <input class="form-input mono" value="<?= e($row['code']) ?>" disabled style="opacity:.6;">
                  </div>
                  <div class="form-group" style="flex:1;min-width:140px;margin:0;">
                    <label class="form-label">ชื่อประเภทลา *</label>
                    <input class="form-input" name="name" value="<?= e($row['name']) ?>" required>
                  </div>
                  <div class="form-group" style="width:110px;margin:0;">
                    <label class="form-label">โควต้า (วัน/ปี)</label>
                    <input class="form-input" type="number" name="quota_days" value="<?= (int) $row['quota_days'] ?>" min="0" placeholder="0=ไม่จำกัด">
                  </div>
                  <div class="form-group" style="width:90px;margin:0;">
                    <label class="form-label">ลำดับ</label>
                    <input class="form-input" type="number" name="sort_order" value="<?= (int) $row['sort_order'] ?>">
                  </div>
                  <div class="form-group" style="margin:0;display:flex;flex-direction:column;justify-content:flex-end;padding-bottom:2px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;white-space:nowrap;">
                      <input type="checkbox" name="deduct_pay" value="1" <?= $row['deduct_pay'] ? 'checked' : '' ?>
                             style="width:16px;height:16px;accent-color:var(--red);">
                      <span style="color:var(--red);">หักเงินเดือน</span>
                    </label>
                  </div>
                  <button class="btn btn-primary" style="height:40px;"><i class="fa-solid fa-check"></i> บันทึก</button>
                  <button type="button" class="btn btn-ghost" style="height:40px;"
                          onclick="toggleEdit('lt-edit-<?= (int)$row['id'] ?>')">ยกเลิก</button>
                </div>
              </form>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; endif; ?>

        <?php if ($canManage): ?>
        <tr id="lt-add-row" class="hidden edit-row">
          <td colspan="7" style="padding:0;">
            <form method="post" style="padding:14px 16px;background:rgba(245,158,11,.08);border-top:1px solid var(--border);">
              <?= csrf_field() ?>
              <input type="hidden" name="do" value="add_leave_type">
              <input type="hidden" name="tab" value="leave_types">
              <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="width:110px;margin:0;">
                  <label class="form-label" style="color:var(--solar-gold);">รหัส (EN) *</label>
                  <input class="form-input mono" name="code" placeholder="maternity" required
                         pattern="[a-zA-Z0-9_]+" title="ตัวอักษรภาษาอังกฤษและ _" maxlength="30">
                </div>
                <div class="form-group" style="flex:1;min-width:140px;margin:0;">
                  <label class="form-label" style="color:var(--solar-gold);">ชื่อประเภทลา *</label>
                  <input class="form-input" name="name" placeholder="เช่น ลาคลอดบุตร" required>
                </div>
                <div class="form-group" style="width:110px;margin:0;">
                  <label class="form-label">โควต้า (วัน/ปี)</label>
                  <input class="form-input" type="number" name="quota_days" value="0" min="0" placeholder="0=ไม่จำกัด">
                </div>
                <div class="form-group" style="width:80px;margin:0;">
                  <label class="form-label">ลำดับ</label>
                  <input class="form-input" type="number" name="sort_order" value="0">
                </div>
                <div class="form-group" style="margin:0;display:flex;flex-direction:column;justify-content:flex-end;padding-bottom:2px;">
                  <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;white-space:nowrap;">
                    <input type="checkbox" name="deduct_pay" value="1"
                           style="width:16px;height:16px;accent-color:var(--red);">
                    <span style="color:var(--red);">หักเงินเดือน</span>
                  </label>
                </div>
                <button class="btn btn-primary" style="height:40px;"><i class="fa-solid fa-plus"></i> เพิ่ม</button>
                <button type="button" class="btn btn-ghost" style="height:40px;"
                        onclick="toggleEdit('lt-add-row')">ยกเลิก</button>
              </div>
            </form>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<style>
.hidden { display: none !important; }
.edit-row > td { background: rgba(245, 158, 11, 0.03); }
</style>
<script>
function toggleEdit(id) {
    document.getElementById(id).classList.toggle('hidden');
}
function showAdd(id) {
    document.querySelectorAll('.edit-row').forEach(r => r.classList.add('hidden'));
    var el = document.getElementById(id);
    el.classList.remove('hidden');
    var inp = el.querySelector('input:not([type=hidden]):not([disabled])');
    if (inp) inp.focus();
}
</script>

<?php require __DIR__ . '/app/layout_footer.php'; ?>
