<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\PreviewEmailTemplateRequest;
use App\Http\Requests\Cms\StoreEmailTemplateRequest;
use App\Http\Requests\Cms\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Notifications\CmsEmailTemplateTestMail;
use App\Services\AuditService;
use App\Services\CmsAccess;
use App\Services\EmailHtmlSanitizer;
use App\Services\EmailMessagePresenter;
use App\Services\EmailTemplatePreviewService;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class EmailTemplateController extends Controller
{
    /** @var list<string> */
    private const STARTER_KEYS = ['inquiry_received', 'general_response', 'follow_up', 'inquiry_reply'];

    public function __construct(
        private AuditService $audit,
        private CmsAccess $access,
        private EmailHtmlSanitizer $sanitizer,
        private EmailMessagePresenter $presenter,
        private EmailTemplatePreviewService $previews,
        private TemplateRenderer $renderer,
    ) {}

    private function authorize(string $permission): void
    {
        abort_unless(auth()->check() && $this->access->allows(auth()->user(), $permission), 403);
    }

    public function index(string $locale, Request $request): Response
    {
        $this->authorize('email_templates.view');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['', 'active', 'inactive'])],
            'locale' => ['nullable', 'string', Rule::in(array_merge([''], config('website.locales', ['ar', 'en'])))],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $localeFilter = (string) ($filters['locale'] ?? '');

        $stats = [
            'total' => EmailTemplate::query()->count(),
            'active' => EmailTemplate::query()->where('is_active', true)->count(),
            'inactive' => EmailTemplate::query()->where('is_active', false)->count(),
            'recent' => EmailTemplate::query()->where('updated_at', '>=', now()->subDays(14))->count(),
        ];

        $query = EmailTemplate::query()->with('translations');

        if ($search !== '') {
            $needle = '%'.addcslashes($search, '%').'%';
            $query->where(function ($query) use ($needle): void {
                $query->where('key', 'like', $needle)
                    ->orWhereHas('translations', function ($query) use ($needle): void {
                        $query->where('name', 'like', $needle)
                            ->orWhere('description', 'like', $needle)
                            ->orWhere('subject', 'like', $needle);
                    });
            });
        }

        if ($status !== '') {
            $query->where('is_active', $status === 'active');
        }

        if ($localeFilter !== '') {
            $query->whereHas('translations', fn ($query) => $query->where('locale', $localeFilter));
        }

        $templates = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $user = auth()->user();

        return Inertia::render('Cms/EmailTemplates/Index', [
            'templates' => [
                'data' => $templates->map(fn (EmailTemplate $template) => $this->serializeTemplate($template))->all(),
                'links' => $templates->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                    'from' => $templates->firstItem(),
                    'to' => $templates->lastItem(),
                ],
            ],
            'stats' => $stats,
            'filters' => ['q' => $search, 'status' => $status, 'locale' => $localeFilter],
            'locales' => config('website.locales', ['ar', 'en']),
            'variables' => $this->renderer->availableVariables(),
            'variableCatalog' => $this->previews->variableCatalog($locale),
            'starters' => $this->starters(),
            'guideExample' => $this->guideExample($locale),
            'strings' => (array) __('email_templates', [], $locale),
            'can' => [
                'create' => $user !== null && $this->access->allows($user, 'email_templates.create'),
                'update' => $user !== null && $this->access->allows($user, 'email_templates.update'),
                'delete' => $user !== null && $this->access->allows($user, 'email_templates.delete'),
            ],
        ]);
    }

    public function store(string $locale, StoreEmailTemplateRequest $request): RedirectResponse
    {
        $this->authorize('email_templates.create');

        $validated = $request->validated();
        $validated['translations'] = $this->sanitizeTranslations($validated['translations']);

        $template = DB::transaction(function () use ($validated): EmailTemplate {
            $template = EmailTemplate::create([
                'key' => $validated['key'],
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
            $this->syncTranslations($template, $validated['translations']);

            return $template;
        });

        $this->audit->record('email_templates.create', 'email_templates', (string) $template->id, extra: [
            'key' => $template->key,
        ]);

        return back()->with('flash', ['success' => __('email_templates.created', [], $locale)]);
    }

    public function update(string $locale, EmailTemplate $template, UpdateEmailTemplateRequest $request): RedirectResponse
    {
        $this->authorize('email_templates.update');

        $validated = $request->validated();
        $validated['translations'] = $this->sanitizeTranslations($validated['translations']);
        $before = $this->snapshot($template);

        DB::transaction(function () use ($template, $validated): void {
            $template->update([
                'is_active' => $validated['is_active'] ?? false,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
            $this->syncTranslations($template, $validated['translations']);
        });

        $this->audit->record('email_templates.update', 'email_templates', (string) $template->id, $before, $this->snapshot($template->fresh()));

        return back()->with('flash', ['success' => __('email_templates.updated', [], $locale)]);
    }

    public function destroy(string $locale, EmailTemplate $template): RedirectResponse
    {
        $this->authorize('email_templates.delete');

        $key = $template->key;
        $template->delete();
        $this->audit->record('email_templates.delete', 'email_templates', (string) $template->id, extra: ['key' => $key]);

        return back()->with('flash', ['success' => __('email_templates.deleted', [], $locale)]);
    }

    public function duplicate(string $locale, EmailTemplate $template, Request $request): RedirectResponse
    {
        $this->authorize('email_templates.create');

        $validated = $request->validate([
            'key' => ['sometimes', 'required', 'string', 'max:96', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:email_templates,key'],
        ]);

        $key = $validated['key'] ?? $this->nextAvailableKey($template->key);

        $copy = DB::transaction(function () use ($template, $key): EmailTemplate {
            $copy = EmailTemplate::create([
                'key' => $key,
                'is_active' => $template->is_active,
                'sort_order' => $template->sort_order,
            ]);

            foreach ($template->translations as $translation) {
                $copy->translations()->create([
                    'locale' => $translation->locale,
                    'name' => $translation->name,
                    'description' => $translation->description,
                    'subject' => $translation->subject,
                    'body' => $this->cleanBody($translation->body),
                ]);
            }

            return $copy;
        });

        $this->audit->record('email_templates.duplicate', 'email_templates', (string) $copy->id, extra: [
            'key' => $copy->key,
            'source' => $template->key,
        ]);

        return back()->with('flash', ['success' => __('email_templates.duplicated', [], $locale)]);
    }

    public function setStatus(string $locale, EmailTemplate $template, Request $request): RedirectResponse
    {
        $this->authorize('email_templates.update');

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $before = $this->snapshot($template);
        $template->update(['is_active' => $validated['is_active']]);

        $this->audit->record('email_templates.update', 'email_templates', (string) $template->id, $before, $this->snapshot($template->fresh()), extra: [
            'action' => 'status_changed',
        ]);

        return back()->with('flash', ['success' => __('email_templates.status_changed', [], $locale)]);
    }

    public function preview(string $locale, PreviewEmailTemplateRequest $request): JsonResponse
    {
        $this->authorize('email_templates.view');

        $validated = $request->validated();
        $locale = (string) ($validated['locale'] ?? $locale);
        $template = isset($validated['template_id'])
            ? EmailTemplate::query()->with('translations')->findOrFail((int) $validated['template_id'])
            : null;
        $key = $template?->key ?: ($template === null ? 'draft' : 'inquiry_reply');
        $subject = $request->exists('subject') ? (string) ($validated['subject'] ?? '') : null;
        $body = $request->exists('body') ? (string) ($validated['body'] ?? '') : null;
        $imagesBlocked = (bool) ($validated['images_blocked'] ?? false);

        return response()->json($this->previews->build($template, $key, $locale, $subject, $body, $imagesBlocked));
    }

    public function sendTest(string $locale, PreviewEmailTemplateRequest $request): JsonResponse
    {
        $this->authorize('email_templates.view');

        $validated = $request->validated();
        $locale = (string) ($validated['locale'] ?? $locale);
        $template = isset($validated['template_id'])
            ? EmailTemplate::query()->with('translations')->findOrFail((int) $validated['template_id'])
            : null;
        $key = $template?->key ?: ($template === null ? 'draft' : 'inquiry_reply');
        $subject = $request->exists('subject') ? (string) ($validated['subject'] ?? '') : null;
        $body = $request->exists('body') ? (string) ($validated['body'] ?? '') : null;
        $preview = $this->previews->build($template, $key, $locale, $subject, $body);
        $recipient = trim((string) (auth()->user()?->email ?? ''));

        abort_if($recipient === '', 422, 'The signed-in administrator has no email address.');

        Mail::to($recipient)->send(new CmsEmailTemplateTestMail(
            (string) $preview['subject'],
            (string) $preview['body'],
            $locale,
        ));

        return response()->json([
            'success' => true,
            'recipient' => $recipient,
            'locale' => $locale,
        ]);
    }

    /** @return array<string, mixed> */
    private function serializeTemplate(EmailTemplate $template): array
    {
        return [
            'id' => $template->id,
            'key' => $template->key,
            'active' => $template->is_active,
            'sortOrder' => $template->sort_order,
            'updatedAt' => $template->updated_at?->toISOString(),
            'locales' => $template->translations->pluck('locale')->values()->all(),
            'translations' => $template->translations->mapWithKeys(fn ($translation) => [
                $translation->locale => [
                    'name' => $translation->name,
                    'description' => $translation->description,
                    'subject' => $translation->subject,
                    'body' => $this->presenter->forEditor($translation->body),
                ],
            ])->all(),
        ];
    }

    /**
     * @return list<array{key: string, translations: array<string, array<string, string|null>>}>
     */
    private function starters(): array
    {
        $starters = [];

        foreach (self::STARTER_KEYS as $key) {
            $definitions = (array) config('email.default_templates.'.$key, []);
            $translations = [];

            foreach (config('website.locales', ['ar', 'en']) as $locale) {
                $definition = (array) ($definitions[$locale] ?? []);
                if ($definition === []) {
                    continue;
                }

                $translations[$locale] = [
                    'name' => (string) ($definition['name'] ?? $key),
                    'description' => $definition['description'] ?? null,
                    'subject' => (string) ($definition['subject'] ?? ''),
                    'body' => $this->presenter->forEditor((string) ($definition['body'] ?? '')),
                ];
            }

            if ($translations !== []) {
                $starters[] = ['key' => $key, 'translations' => $translations];
            }
        }

        return $starters;
    }

    private function guideExample(string $locale): string
    {
        $definitions = (array) config('email.default_templates.inquiry_reply', []);
        $definition = (array) ($definitions[$locale] ?? $definitions['en'] ?? []);
        $subject = (string) ($definition['subject'] ?? '');
        $body = (string) ($definition['body'] ?? '');

        if ($subject === '' && $body === '') {
            return '';
        }

        return trim(__('email_templates.review_subject', [], $locale).': '.$subject."\n\n".$body);
    }

    /** @param array<string, array<string, mixed>> $translations @return array<string, array<string, mixed>> */
    private function sanitizeTranslations(array $translations): array
    {
        foreach ($translations as $code => $values) {
            if (is_string($values['body'] ?? null)) {
                $values['body'] = $this->cleanBody($values['body']);
            }
            $translations[$code] = $values;
        }

        return $translations;
    }

    private function cleanBody(string $body): string
    {
        return $this->presenter->isHtml($body) ? $this->sanitizer->clean($body) : $body;
    }

    private function nextAvailableKey(string $base): string
    {
        $stem = substr($base, 0, 80);

        for ($attempt = 1; $attempt <= 99; $attempt++) {
            $candidate = $stem.'_copy'.$attempt;
            if (! EmailTemplate::query()->where('key', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $stem.'_copy_'.Str::lower(Str::random(6));
    }

    /** @param array<string, array<string, mixed>> $translations */
    private function syncTranslations(EmailTemplate $template, array $translations): void
    {
        foreach ($translations as $code => $values) {
            if (! in_array($code, config('website.locales', ['ar', 'en']), true)) {
                continue;
            }

            $template->translations()->updateOrCreate(['locale' => $code], [
                'name' => $values['name'],
                'description' => $values['description'] ?? null,
                'subject' => $values['subject'],
                'body' => $values['body'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(EmailTemplate $template): array
    {
        return [
            'key' => $template->key,
            'is_active' => $template->is_active,
            'sort_order' => $template->sort_order,
            'translations' => $template->translations->map(fn ($translation) => [
                'locale' => $translation->locale,
                'name' => $translation->name,
                'subject' => $translation->subject,
                'body' => $translation->body,
            ])->all(),
        ];
    }
}
