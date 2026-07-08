<?php
declare(strict_types=1);
/** modules/leads/scan-start.php — Sihirbazdan gelen config'i tarama işi olarak oluşturur. */
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/lead-scan.php';
auth_boot();
require_permission('leads.create');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('modules/leads/scan.php'); }
csrf_check();

$config = lead_scan_normalize_config($_POST);
if (empty($config['terms'])) { flash('error', 'En az bir sektör/kelime seçmelisiniz.'); redirect('modules/leads/scan.php'); }
if (empty($config['districts']) && $config['city'] === '') { flash('error', 'En az bir bölge seçmelisiniz.'); redirect('modules/leads/scan.php'); }
if ((int) $config['target_limit'] <= 0) { $config['target_limit'] = (int) app_setting_get('lead_scan_target_default', '100'); }

$id = lead_scan_create($config, current_user_id());
if ($id <= 0) { flash('error', 'Tarama işi oluşturulamadı.'); redirect('modules/leads/scan.php'); }
log_activity('lead_scan_start', 'lead_scan', $id, null, 'success', 'Lead tarama başlatıldı (' . count($config['terms']) . ' terim)');
flash('success', 'Lead tarama işi oluşturuldu. Chrome eklentisi bu işi işleyecek.');
redirect('modules/leads/scan-view.php?id=' . $id);
