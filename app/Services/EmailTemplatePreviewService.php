<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailTemplate;

/**
 * Builds read-only previews from the same rendering path used to prepare real
 * emails. No mail transport is touched and only fixed sample values are used.
 */
final class EmailTemplatePreviewService
{
    /** @var array<string, array{label: string, description: string}> */
    private const VARIABLE_TRANSLATIONS = [
        'sender_name' => [
            'label' => 'email_templates.variables_sender_name_label',
            'description' => 'email_templates.variables_sender_name_description',
        ],
        'sender_email' => [
            'label' => 'email_templates.variables_sender_email_label',
            'description' => 'email_templates.variables_sender_email_description',
        ],
        'inquiry_subject' => [
            'label' => 'email_templates.variables_inquiry_subject_label',
            'description' => 'email_templates.variables_inquiry_subject_description',
        ],
        'inquiry_reference' => [
            'label' => 'email_templates.variables_inquiry_reference_label',
            'description' => 'email_templates.variables_inquiry_reference_description',
        ],
        'reply_message' => [
            'label' => 'email_templates.variables_reply_message_label',
            'description' => 'email_templates.variables_reply_message_description',
        ],
        'organization_name' => [
            'label' => 'email_templates.variables_organization_name_label',
            'description' => 'email_templates.variables_organization_name_description',
        ],
        'current_date' => [
            'label' => 'email_templates.variables_current_date_label',
            'description' => 'email_templates.variables_current_date_description',
        ],
    ];

    public function __construct(
        private EmailTemplateContentResolver $content,
        private EmailMessagePresenter $presenter,
        private SiteSettings $settings,
        private TemplateRenderer $renderer,
    ) {}

    /**
     * @return array<string, array{key: string, label: string, description: string, example: string, token: string}>
     */
    public function variableCatalog(string $locale): array
    {
        $samples = $this->sampleVariables($locale);
        $catalog = [];

        foreach ($this->renderer->availableVariables() as $key => $description) {
            $translations = self::VARIABLE_TRANSLATIONS[$key] ?? null;
            $labelKey = $translations['label'] ?? null;
            $descriptionKey = $translations['description'] ?? null;

            $catalog[$key] = [
                'key' => $key,
                'label' => $labelKey === null ? $key : __($labelKey, [], $locale),
                'description' => $descriptionKey === null ? $description : __($descriptionKey, [], $locale),
                'example' => $samples[$key] ?? '',
                'token' => '{{ '.$key.' }}',
            ];
        }

        return $catalog;
    }

    /**
     * @return array<string, string>
     */
    public function sampleVariables(string $locale): array
    {
        return [
            'sender_name' => (string) __('website.cms.preview_sender_name', [], $locale),
            'sender_email' => 'sender@example.com',
            'inquiry_subject' => (string) __('website.cms.preview_inquiry_subject', [], $locale),
            'inquiry_reference' => '01JPREVIEW00000000000000000',
            'reply_message' => (string) __('website.cms.preview_reply_message', [], $locale),
            'organization_name' => (string) $this->settings->value('organization.name', config('app.name'), $locale),
            'current_date' => now()->locale($locale)->translatedFormat('j F Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        ?EmailTemplate $template,
        string $key,
        string $locale,
        ?string $subject = null,
        ?string $body = null,
        bool $imagesBlocked = false,
    ): array {
        $variables = $this->sampleVariables($locale);
        $renderedSubject = $subject !== null
            ? $this->content->render($subject, $variables)
            : $this->content->field($template, $key, 'subject', $locale, $variables);
        $renderedBody = $body !== null
            ? $this->content->render($body, $variables)
            : $this->content->field($template, $key, 'body', $locale, $variables);

        $sender = [
            'name' => (string) config('mail.from.name', ''),
            'address' => (string) config('mail.from.address', ''),
        ];
        $recipient = 'recipient@example.test';
        $templateKey = $template?->key ?: $key;

        return [
            'subject' => $renderedSubject,
            'body' => $renderedBody,
            'html' => $this->presenter->previewDocument(
                $renderedSubject,
                $renderedBody,
                $locale,
                $sender,
                $recipient,
                $templateKey,
                $imagesBlocked,
            ),
            'plainBody' => $this->presenter->plainText($renderedBody),
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'locale' => $locale,
            'sender' => $sender,
            'recipient' => $recipient,
            'sampleVariables' => $variables,
            'templateKey' => $templateKey,
            'templateId' => $template?->id,
            'isDraft' => $subject !== null || $body !== null,
        ];
    }
}
