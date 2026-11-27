<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintContactMethod;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Http\Requests\StoreComplaintRequest;
use App\Services\ComplaintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

final class ComplaintController extends PublicController
{
    public function index(string $locale): Response
    {
        $localized = static function (string $name, string $path) use ($locale): string {
            return Route::has($name) ? route($name, ['locale' => $locale]) : $path;
        };

        $config = $this->config($locale);
        $config['storeUrl'] = $localized('public.complaints.store', "/{$locale}/complaints");
        $config['complaintsUrl'] = $localized('public.complaints', "/{$locale}/complaints");

        return Inertia::render('Public/Complaints', [
            'complaintConfig' => $config,
            'categories' => $config['categories'],
            'contactMethods' => $config['contactMethods'],
            'attachmentLimits' => $config['attachmentLimits'],
            'storeUrl' => $config['storeUrl'],
            'complaintsUrl' => $config['complaintsUrl'],
            'submittedReference' => session('flash.reference'),
            'meta' => $this->seo(
                $locale,
                (string) __('website.complaints.title', [], $locale),
                (string) __('website.complaints.meta_description', [], $locale),
                path: "{$locale}/complaints",
            ),
        ]);
    }

    public function store(StoreComplaintRequest $request, ComplaintService $service): RedirectResponse
    {
        $locale = $request->string('locale')->toString();
        $complaint = $service->submit($request->validated(), $request);

        return back()->with('flash', [
            'success' => (string) __('website.complaints.submitted', ['reference' => $complaint->reference], $locale),
            'reference' => $complaint->reference,
        ]);
    }

    /** @return array<string, mixed> */
    private function config(string $locale): array
    {
        $categories = [];
        $categoryLabels = __('website.complaints.categories', [], $locale);

        foreach ((array) config('complaints.categories', ComplaintCategory::values()) as $value) {
            $value = (string) $value;
            $label = is_array($categoryLabels) ? ($categoryLabels[$value] ?? null) : null;
            $categories[] = [
                'value' => $value,
                'label' => is_string($label) && $label !== ''
                    ? $label
                    : str_replace('_', ' ', $value),
            ];
        }

        $contactMethods = [];
        $contactMethodLabels = __('website.complaints.contact_methods', [], $locale);

        foreach ((array) config('complaints.contact_methods', ComplaintContactMethod::values()) as $value) {
            $value = (string) $value;
            $label = is_array($contactMethodLabels) ? ($contactMethodLabels[$value] ?? null) : null;
            $contactMethods[] = ['value' => $value, 'label' => is_string($label) && $label !== '' ? $label : $value];
        }

        /** @var array<string, mixed> $attachments */
        $attachments = (array) config('complaints.attachments', []);

        return [
            'statuses' => ComplaintStatus::values(),
            'categories' => $categories,
            'contactMethods' => $contactMethods,
            'priorities' => ComplaintPriority::values(),
            'attachmentLimits' => [
                'maxFiles' => (int) ($attachments['max_files'] ?? 0),
                'maxFileSizeKb' => (int) ($attachments['max_file_size_kb'] ?? 0),
                'allowedExtensions' => array_values((array) ($attachments['allowed_extensions'] ?? [])),
                'allowedMimes' => array_values((array) ($attachments['allowed_mimes'] ?? [])),
            ],
            'attachments' => $attachments,
        ];
    }
}
