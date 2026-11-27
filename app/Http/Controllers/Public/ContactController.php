<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Services\InquiryMailService;
use App\Services\PublicContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class ContactController extends PublicController
{
    public function index(string $locale, PublicContentService $content): Response
    {
        $copy = $locale === 'ar'
            ? [
                'title' => 'اتصل بنا',
                'description' => 'بيانات التواصل الرسمية ووسائل الاتصال بجمعية العمل التشاركي في الأزمات والكوارث.',
            ]
            : [
                'title' => 'Contact us',
                'description' => 'Official contact details and enquiry form for PCDR.',
            ];

        return Inertia::render('Public/Contact', [
            'contact' => $content->contact($locale),
            'meta' => $this->seo(
                $locale,
                $copy['title'],
                $copy['description'],
                path: "{$locale}/contact",
            ),
        ]);
    }

    public function store(
        string $locale,
        StoreContactMessageRequest $request,
        InquiryMailService $mailService,
    ): RedirectResponse {
        $validated = $request->validated();

        $message = ContactMessage::query()->create([
            'reference' => (string) Str::ulid(),
            'category' => 'general',
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'subject' => (string) $validated['subject'],
            'message' => (string) $validated['message'],
            'locale' => $locale,
            'consent' => true,
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'user_agent' => mb_strcut((string) $request->userAgent(), 0, 255),
            'status' => 'new',
        ]);

        $mailService->sendInternalNotification($message, 'contact');

        return back()->with('flash', [
            'success' => $locale === 'ar'
                ? 'تم إرسال رسالتك بنجاح. شكراً لتواصلك معنا.'
                : 'Your message has been sent successfully. Thank you for contacting us.',
            'reference' => $message->reference,
        ]);
    }
}
