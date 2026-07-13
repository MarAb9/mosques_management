<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ErrorHandler;
use App\Repositories\MosqueRepository;
use App\Validators\MosqueValidator;
use PDOException;

/**
 * Mosque create/update/delete flows — the business logic previously
 * inlined in add_mosque.php, edit_mosque.php, delete_mosque.php and
 * bulk_delete_mosques.php. Validation order, error keys, messages, and
 * file-handling order are preserved exactly.
 */
final class MosqueWriteService
{
    public function __construct(
        private readonly MosqueRepository $mosques,
        private readonly MosqueFormService $form,
        private readonly MosqueValidator $validator,
        private readonly UploadService $uploads,
        private readonly ErrorHandler $errors,
    ) {
    }

    /**
     * Create a mosque from raw POST data.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{success: bool, formData: array<string, mixed>, errors: array<string, string>}
     */
    public function create(array $post, array $files): array
    {
        $errors = [];
        $formData = [];

        try {
            $formData = $this->form->processFormData($post);
            $errors = $this->validator->requiredFields($formData, (string) $formData['admin_type']);

            // Check for duplicate national code
            if (empty($errors['national_code']) && $this->mosques->nationalCodeExists((string) $formData['national_code'])) {
                $errors['national_code'] = 'الرمز الوطني مسجل مسبقاً';
            }

            if (empty($errors)) {
                $imagePath = null;

                if (!empty($files['main_image']['name'])) {
                    $uploadErrors = $this->uploads->validateImage($files['main_image']);

                    if (!empty($uploadErrors)) {
                        $errors['main_image'] = implode('<br>', $uploadErrors);
                    } else {
                        $imagePath = $this->uploads->storeMosqueImage($files['main_image']);
                        if ($imagePath === null) {
                            $errors['main_image'] = 'فشل تحميل الملف. يرجى المحاولة مرة أخرى';
                        }
                    }
                }

                if (empty($errors)) {
                    $formData['main_image'] = $imagePath;
                    $this->mosques->insert($formData);

                    return ['success' => true, 'formData' => $formData, 'errors' => []];
                }
            }
        } catch (PDOException $e) {
            $this->errors->log($e);
            $errors['database'] = 'حدث خطأ في النظام. يرجى المحاولة لاحقاً';
        }

        return ['success' => false, 'formData' => $formData, 'errors' => $errors];
    }

    /**
     * Update a mosque from raw POST data.
     *
     * @param array<string, mixed> $mosque existing row
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{success: bool, formData: array<string, mixed>, errors: array<string, string>}
     */
    public function update(array $mosque, array $post, array $files): array
    {
        $errors = [];
        $formData = [];

        try {
            $formData = $this->form->processFormData($post, $mosque['main_image']);
            $errors = $this->validator->requiredFields($formData, (string) $formData['admin_type']);

            if (empty($errors)) {
                if (!empty($files['main_image']['name'])) {
                    $uploadErrors = $this->uploads->validateImage($files['main_image']);

                    if (!empty($uploadErrors)) {
                        $errors['main_image'] = implode('<br>', $uploadErrors);
                    } else {
                        $newPath = $this->uploads->storeMosqueImage($files['main_image']);
                        if ($newPath === null) {
                            $errors['main_image'] = 'فشل تحميل الملف. يرجى المحاولة مرة أخرى';
                        } else {
                            // Delete old image if it exists (legacy order:
                            // only after a successful move).
                            $this->uploads->deleteMosqueImage($formData['main_image']);
                            $formData['main_image'] = $newPath;
                        }
                    }
                }

                // Handle image removal if requested
                if (isset($post['remove_image'])) {
                    $this->uploads->deleteMosqueImage($formData['main_image']);
                    $formData['main_image'] = null;
                }

                if (empty($errors)) {
                    $this->mosques->update($mosque['registration_number'], $formData);

                    return ['success' => true, 'formData' => $formData, 'errors' => []];
                }
            }
        } catch (PDOException $e) {
            $this->errors->log($e);
            $errors['database'] = 'حدث خطأ أثناء تحديث البيانات. يرجى المحاولة لاحقاً';
        }

        return ['success' => false, 'formData' => $formData, 'errors' => $errors];
    }

    /**
     * Delete mosques by registration numbers.
     *
     * @param list<mixed> $registrationNumbers
     */
    public function delete(array $registrationNumbers): int
    {
        $this->mosques->deleteByRegistrationNumbers($registrationNumbers);

        return count($registrationNumbers);
    }
}
