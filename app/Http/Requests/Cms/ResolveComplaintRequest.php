<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Models\Complaint;
use App\Models\User;
use App\Services\CmsAccess;
use Illuminate\Foundation\Http\FormRequest;

final class ResolveComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && app(CmsAccess::class)->allows($user, 'complaints.resolve')
            && $this->canViewComplaint($user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution_details' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['resolution_details' => (string) __('cms_complaint_resolution_details')];
    }

    private function canViewComplaint(User $user): bool
    {
        $access = app(CmsAccess::class);

        if ($access->allows($user, 'complaints.view_all')) {
            return true;
        }

        if (! $access->allows($user, 'complaints.view_assigned')) {
            return false;
        }

        $complaint = $this->route('complaint');

        if ($complaint instanceof Complaint) {
            return (int) $complaint->assigned_to === (int) $user->id;
        }

        if (is_numeric($complaint)) {
            return Complaint::query()->whereKey((int) $complaint)->where('assigned_to', $user->id)->exists();
        }

        return false;
    }
}
