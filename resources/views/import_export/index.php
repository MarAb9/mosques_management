<?php
/**
 * Import/Export page (legacy import_export.php markup, verbatim).
 * Expects: $successMessage, $errorMessage, $isAdmin, $statuses,
 *          $fridayPrayers, $communities, $literacyPrograms,
 *          $guidancePrograms, $guideImams
 */
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Alerts -->
            <?php if ($successMessage !== null): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $view->e($successMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage !== null): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $view->e($errorMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Main Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>نظام استيراد وتصدير بيانات المساجد
                    </h4>
                </div>

                <div class="card-body">
                    <!-- Import Section -->
                    <?php if ($canImport ?? $isAdmin): ?>
                        <div class="mb-5">
                            <h5 class="mb-3 text-dark">
                                <i class="fas fa-file-import text-primary me-2"></i>استيراد البيانات
                            </h5>
                            <form method="POST" action="" enctype="multipart/form-data" class="row g-3" id="importPreviewForm">
                                <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                                <!-- KEEP YOUR EXISTING IMPORT FORM -->
                                <div class="col-md-8">
                                    <label for="import_file" class="form-label">اختر ملف Excel</label>
                                    <input type="file" class="form-control" id="import_file" name="import_file" accept=".xlsx, .xls" required>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" name="preview_import" value="1" class="btn btn-primary w-100">
                                        <i class="fas fa-eye me-2"></i>استيراد
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- IMPORT RESTRICTION FOR CLIENTS -->
                        <div class="alert alert-info mb-5">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>الاستيراد متاح للمسؤولين فقط:</strong> يمكنك تصدير البيانات ولكن لا يمكنك استيرادها.
                        </div>
                    <?php endif; ?>


                    <?php if (!empty($importPreview)): ?>
                        <div class="card border-primary mb-5">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-table me-2"></i>معاينة الاستيراد قبل التنفيذ</span>
                                <?php if (!empty($importPreview['errors'])): ?>
                                    <a class="btn btn-light btn-sm" href="import_export.php?import_error_report=<?= urlencode((string) $importPreview['token']) ?>">
                                        <i class="fas fa-file-csv me-1"></i>تنزيل تقرير الأخطاء
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 mb-3 text-center">
                                    <div class="col-md-3"><div class="p-3 bg-light rounded"><div class="fw-bold"><?= number_format((int) ($importPreview['total_rows'] ?? 0)) ?></div><small>إجمالي الصفوف</small></div></div>
                                    <div class="col-md-3"><div class="p-3 bg-success-subtle rounded"><div class="fw-bold text-success"><?= number_format((int) ($importPreview['valid_rows'] ?? 0)) ?></div><small>جاهزة للاستيراد</small></div></div>
                                    <div class="col-md-3"><div class="p-3 bg-warning-subtle rounded"><div class="fw-bold text-warning"><?= number_format((int) ($importPreview['duplicate_rows'] ?? 0)) ?></div><small>مكررة</small></div></div>
                                    <div class="col-md-3"><div class="p-3 bg-danger-subtle rounded"><div class="fw-bold text-danger"><?= number_format((int) ($importPreview['skipped_rows'] ?? 0)) ?></div><small>أخطاء/ناقصة</small></div></div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle">
                                        <thead class="table-light"><tr><th>السطر</th><th>المسجد</th><th>الرمز الوطني</th><th>الحالة</th><th>ملاحظة</th></tr></thead>
                                        <tbody>
                                            <?php foreach (($importPreview['preview_rows'] ?? []) as $row): ?>
                                                <?php $badge = ($row['status'] ?? '') === 'valid' ? 'success' : ((($row['status'] ?? '') === 'duplicate') ? 'warning' : 'danger'); ?>
                                                <tr>
                                                    <td><?= $view->e((string) ($row['row'] ?? '')) ?></td>
                                                    <td><?= $view->e((string) ($row['mosque_name'] ?? '')) ?></td>
                                                    <td><?= $view->e((string) ($row['national_code'] ?? '')) ?></td>
                                                    <td><span class="badge bg-<?= $badge ?>"><?= $view->e((string) ($row['status'] ?? '')) ?></span></td>
                                                    <td><?= $view->e((string) ($row['message'] ?? '')) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <form method="POST" action="import_export.php" class="d-flex justify-content-end gap-2 mt-3 js-confirm-submit" data-confirm="هل تريد تنفيذ الاستيراد الآن؟">
                                    <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                                    <input type="hidden" name="import_token" value="<?= $view->e((string) $importPreview['token']) ?>">
                                    <a href="import_export.php" class="btn btn-outline-secondary">إلغاء</a>
                                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>تأكيد الاستيراد</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (($canImport ?? $isAdmin) && !empty($lastImport)): ?>
                        <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3 mb-5">
                            <div>
                                <strong><i class="fas fa-rotate-left me-1"></i>آخر استيراد قابل للتراجع:</strong>
                                <?= $view->e((string) ($lastImport['original_name'] ?? '')) ?> — <?= number_format((int) ($lastImport['row_count'] ?? 0)) ?> سجل
                            </div>
                            <form method="POST" action="import_export.php" class="js-confirm-submit" data-confirm="سيتم حذف السجلات التي أضيفت في آخر استيراد. هل تريد المتابعة؟">
                                <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                                <button type="submit" name="rollback_last_import" value="1" class="btn btn-outline-danger btn-sm">تراجع عن آخر استيراد</button>
                            </form>
                        </div>
                    <?php endif; ?>
                    <!-- Export Section -->
                    <div>
                        <h5 class="mb-3 text-dark">
                            <i class="fas fa-file-export text-success me-2"></i>تصدير البيانات
                        </h5>

                        <div class="d-grid gap-2">
                            <a href="import_export.php?export=1" class="btn btn-success">
                                <i class="fas fa-file-excel me-2"></i>تصدير جميع البيانات
                            </a>

                            <a href="import_export.php?export=1&no_location=1&group_by_guide=1" class="btn btn-warning">
                                <i class="fas fa-map-marker-alt me-2"></i>تصدير مساجد الإمام المرشد غير محددة الموقع (Excel)
                            </a>

                            <a href="import_export.php?export=1&no_location=1&group_by_guide=1&format=word" class="btn btn-info text-white">
                                <i class="fas fa-file-word me-2"></i>تصدير مساجد الإمام المرشد غير محددة الموقع (Word)
                            </a>

                            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#filterModal">
                                <i class="fas fa-filter me-2"></i>تصدير مخصص
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="card-footer bg-light">
                    <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#instructionsCollapse">
                        <i class="fas fa-info-circle me-2 text-info"></i>تعليمات الاستيراد والتصدير
                    </button>

                    <div class="collapse mt-2" id="instructionsCollapse">
                        <div class="small">
                            <ul class="mb-0">
                                <li>يجب أن يحتوي ملف الاستيراد على الأعمدة الأساسية (B-E)</li>
                                <li>البيانات في العمود E (الرمز الوطني) يجب أن تكون فريدة</li>
                                <li>يجب أن يكون الصف الأول يحتوي على العناوين</li>
                                <li>يمكنك تصدير البيانات كاملة أو باستخدام عوامل التصفية</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="filterModalLabel">
                    <i class="fas fa-filter me-2"></i>تصدير مخصص
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="exportForm" action="import_export.php" method="GET">
                <input type="hidden" name="export" value="1">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="exportStatus" class="form-label">حالة المسجد</label>
                            <select class="form-select" id="exportStatus" name="status">
                                <option value="">الكل</option>
                                <?php foreach ($statuses as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="exportFriday" class="form-label">صلاة الجمعة</label>
                            <select class="form-select" id="exportFriday" name="friday_prayer">
                                <option value="">الكل</option>
                                <?php foreach ($fridayPrayers as $prayer): ?>
                                <option value="<?= htmlspecialchars($prayer) ?>"><?= htmlspecialchars($prayer) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="exportCommunity" class="form-label">الجماعة</label>
                            <select class="form-select" id="exportCommunity" name="community">
                                <option value="">الكل</option>
                                <?php foreach ($communities as $community): ?>
                                <option value="<?= htmlspecialchars($community) ?>"><?= htmlspecialchars($community) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="exportLiteracy" class="form-label">محو الأمية</label>
                            <select class="form-select" id="exportLiteracy" name="literacy_program">
                                <option value="">الكل</option>
                                <?php foreach ($literacyPrograms as $program): ?>
                                <option value="<?= htmlspecialchars($program) ?>"><?= htmlspecialchars($program) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="exportGuidance" class="form-label">الوعظ والإرشاد</label>
                            <select class="form-select" id="exportGuidance" name="guidance_program">
                                <option value="">الكل</option>
                                <?php foreach ($guidancePrograms as $program): ?>
                                <option value="<?= htmlspecialchars($program) ?>"><?= htmlspecialchars($program) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="exportGuideImam" class="form-label">الإمام المرشد</label>
                            <select class="form-select" id="exportGuideImam" name="guide_imam">
                                <option value="">الكل</option>
                                <?php foreach ($guideImams as $imam): ?>
                                <option value="<?= $imam['id'] ?>"><?= htmlspecialchars($imam['display_name']) ?> (<?= $imam['mosque_count'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6 mb-3">
                            <label for="exportFormat" class="form-label">صيغة الملف</label>
                            <select class="form-select" id="exportFormat" name="format">
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="word">Word (.docx)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="exportNoLocation" name="no_location" value="1">
                                <label class="form-check-label" for="exportNoLocation">تصدير المساجد غير محددة الموقع فقط</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">تصدير البيانات</button>
                </div>
            </form>
        </div>
    </div>
</div>
