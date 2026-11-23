<?php
require_once __DIR__.'/../api/app/controller/ParticipantUserController.php';
$admin=(new AdminController())->requireSuperAdmin();$controller=new ParticipantUserController();
$search=trim((string)($_GET['q']??''));$accounts=$controller->accounts($admin,$search);$available=$controller->available($admin,$search);
include __DIR__.'/part/header.php';
?>
<body class="admin-body"><div class="admin-layout"><?php include __DIR__.'/part/sidebar.php'; ?>
<main class="admin-main">
<section class="topbar"><div><span class="eyebrow">User / بەشداربووان</span><h1>بەڕێوەبردنی هەژمارەکانی User</h1><p class="subtle">بۆ بەشداربووی پەسەندکراو Username و پاسۆرد دروست بکە. ئەم هەژمارە تەنها هەژماری بەشداربووە؛ دەسەڵاتی ئەدمینی پێ نادرێت.</p></div><a class="btn ghost" href="../login.php">چوونەژوورەوەی بەشداربووان</a></section>
<?php if(!empty($_SESSION['admin_flash_success'])): ?><div class="notice success"><?php echo e($_SESSION['admin_flash_success']);unset($_SESSION['admin_flash_success']); ?></div><?php endif; ?>
<?php if(!empty($_SESSION['admin_flash_error'])): ?><div class="notice error"><?php echo e($_SESSION['admin_flash_error']);unset($_SESSION['admin_flash_error']); ?></div><?php endif; ?>
<section class="panel"><div class="section-heading"><div><h2>دروستکردنی User بۆ بەشداربووی پەسەندکراو</h2><p class="subtle">تەنها بۆ ئەو کەسانەی ID ـی فەرمییان هەیە و هێشتا هەژماریان نەدروستکردووە. بۆ خوێندکار/مامۆستا ئیمەیڵ ئارەزوومەندانەیە؛ بۆ فەرمانبەر پێویستە. پاسۆردەکە تەنها بە ڕێگایەکی پارێزراو بدە بە خاوەن هەژمارەکە؛ لە سیستەمدا بە شێوەی Hash هەڵدەگیرێت.</p></div></div>
<form method="post" action="api/participant-users.php" class="modern-form"><?php echo csrf_input(); ?><input type="hidden" name="action" value="create">
<div class="grid two"><label class="field"><span>ID ـی فەرمی</span><select name="issued_id" required><option value="">هەڵبژێرە</option><?php foreach($available as $person): ?><option value="<?php echo e($person['issued_id']); ?>"><?php echo e(display_name($person).' · '.$person['issued_id'].' · '.role_label($person['person_type'])); ?></option><?php endforeach; ?></select></label>
<label class="field"><span>Username</span><input name="username" pattern="[a-zA-Z][a-zA-Z0-9._-]{2,29}" maxlength="30" minlength="3" autocomplete="off" required placeholder="watan123"></label></div>
<div class="grid two"><label class="field"><span>پاسۆردی نوێ (لانیکەم ٨ نووسە)</span><input name="password" type="password" minlength="8" autocomplete="new-password" required></label><label class="field"><span>ئیمەیڵ (بۆ فەرمانبەر پێویستە)</span><input name="email" type="email" autocomplete="off" maxlength="190"></label></div>
<button type="submit" class="btn primary" <?php echo !$available?'disabled':''; ?>><i class="fas fa-user-plus"></i> دروستکردنی هەژماری User</button></form>
</section>
<section class="panel admin-records-panel"><div class="section-heading"><div><h2>هەژمارەکانی بەشداربووان</h2><p class="subtle">ئەو هەژمارانەی پێشتر بە ئیمەیڵ دروستکراون هەروا کار دەکەن. دەتوانیت Username بۆیان دیاری بکەیت.</p></div></div>
<form method="get" class="student-id-search"><label class="field"><span>گەڕان بە ناو، ID، ئیمەیڵ یان Username</span><input name="q" value="<?php echo e($search); ?>" maxlength="100"></label><button class="btn primary" type="submit">گەڕان</button></form>
<?php if(!$accounts): ?><p class="subtle">هێشتا هیچ هەژمارێک نەدۆزرایەوە.</p><?php else: ?><div class="table-scroll"><table class="admin-table"><thead><tr><th>ناوی بەشداربوو</th><th>ID ـی فەرمی</th><th>Username</th><th>ئیمەیڵ</th><th>کردار</th></tr></thead><tbody><?php foreach($accounts as $u): ?><tr>
<td><strong><?php echo e(display_name($u)); ?></strong><small><?php echo e(role_label($u['role'])); ?></small></td><td><?php echo e($u['issued_id']??'—'); ?></td><td><?php echo e($u['username']?:'—'); ?></td><td><?php echo e($u['email']?:'—'); ?></td>
<td><?php if(empty($u['username'])): ?><form method="post" action="api/participant-users.php" class="rw-inline"><?php echo csrf_input(); ?><input type="hidden" name="action" value="assign_username"><input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>"><input name="username" pattern="[a-zA-Z][a-zA-Z0-9._-]{2,29}" minlength="3" maxlength="30" placeholder="Username" required style="max-width:170px"><button class="btn ghost small" type="submit">دیاریکردن</button></form><?php else: ?>—<?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div><?php endif; ?><p class="subtle">لە هەر بەشێکدا تەنها ١٠٠ ئەنجامی کۆتایی نیشان دەدرێت؛ بۆ زیاتر گەڕان بەکاربهێنە.</p></section>
</main></div></body></html>
