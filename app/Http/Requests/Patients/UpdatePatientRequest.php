<?php

namespace App\Http\Requests\Patients;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Patient|null $patient */
        $patient = $this->route('patient');

        return [
            'mrn' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('patients', 'mrn')->ignore($patient?->id),
            ],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'primary_provider_id' => ['nullable', 'integer', 'exists:users,id'],
            'referring_provider_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:30'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['sometimes', Rule::in(['male', 'female', 'other', 'unknown'])],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'religion' => ['nullable', 'string', 'max:60'],
            'language' => ['nullable', 'string', 'max:60'],
            'race' => ['nullable', 'string', 'max:60'],
            'ethnicity' => ['nullable', 'string', 'max:60'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'drivers_license' => ['nullable', 'string', 'max:50'],
            'phone_cell' => ['nullable', 'string', 'max:30'],
            'phone_home' => ['nullable', 'string', 'max:30'],
            'phone_work' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:60'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'registration_date' => ['nullable', 'date'],
            'registration_type' => ['nullable', 'string', 'max:40'],
            'referral_source' => ['nullable', 'string', 'max:80'],
            'price_level' => ['nullable', 'string', 'max:40'],
            'billing_note' => ['nullable', 'string'],
            'clinical_alert' => ['nullable', 'string'],
            'deceased_at' => ['nullable', 'date'],
            'deceased_reason' => ['nullable', 'string', 'max:255'],

            'contacts' => ['sometimes', 'array'],
            'contacts.*.contact_type' => ['required_with:contacts', Rule::in(['emergency', 'guardian', 'next_of_kin', 'mother', 'father', 'other'])],
            'contacts.*.relationship' => ['nullable', 'string', 'max:60'],
            'contacts.*.full_name' => ['required_with:contacts', 'string', 'max:200'],
            'contacts.*.sex' => ['nullable', Rule::in(['male', 'female', 'other', 'unknown'])],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.address' => ['nullable', 'string', 'max:255'],
            'contacts.*.country' => ['nullable', 'string', 'max:60'],
            'contacts.*.is_primary' => ['sometimes', 'boolean'],

            'consent' => ['sometimes', 'array'],
            'consent.allow_sms' => ['sometimes', 'boolean'],
            'consent.allow_email' => ['sometimes', 'boolean'],
            'consent.allow_voice' => ['sometimes', 'boolean'],
            'consent.allow_mail' => ['sometimes', 'boolean'],
            'consent.hipaa_notice_received' => ['sometimes', 'boolean'],
            'consent.hipaa_message' => ['nullable', 'string', 'max:60'],
            'consent.allow_immunization_registry' => ['sometimes', 'boolean'],
            'consent.allow_health_info_exchange' => ['sometimes', 'boolean'],
            'consent.advance_directive_completed' => ['sometimes', 'boolean'],
            'consent.advance_directive_reviewed_at' => ['nullable', 'date'],

            'employer' => ['sometimes', 'nullable', 'array'],
            'employer.employer_name' => ['required_with:employer', 'string', 'max:255'],
            'employer.occupation' => ['nullable', 'string', 'max:120'],
            'employer.department' => ['nullable', 'string', 'max:120'],
            'employer.employment_status' => ['nullable', 'string', 'max:60'],
            'employer.phone' => ['nullable', 'string', 'max:30'],
            'employer.street' => ['nullable', 'string', 'max:255'],
            'employer.city' => ['nullable', 'string', 'max:120'],
            'employer.state' => ['nullable', 'string', 'max:60'],
            'employer.postal_code' => ['nullable', 'string', 'max:20'],
            'employer.country' => ['nullable', 'string', 'max:60'],

            'insurances' => ['sometimes', 'array'],
            'insurances.*.tier' => ['required_with:insurances', Rule::in(['primary', 'secondary', 'tertiary'])],
            'insurances.*.provider_name' => ['required_with:insurances', 'string', 'max:255'],
            'insurances.*.plan_name' => ['nullable', 'string', 'max:255'],
            'insurances.*.policy_number' => ['nullable', 'string', 'max:100'],
            'insurances.*.group_number' => ['nullable', 'string', 'max:100'],
            'insurances.*.policy_type' => ['nullable', 'string', 'max:40'],
            'insurances.*.subscriber_first_name' => ['nullable', 'string', 'max:100'],
            'insurances.*.subscriber_middle_name' => ['nullable', 'string', 'max:100'],
            'insurances.*.subscriber_last_name' => ['nullable', 'string', 'max:100'],
            'insurances.*.subscriber_relationship' => ['nullable', 'string', 'max:40'],
            'insurances.*.subscriber_dob' => ['nullable', 'date'],
            'insurances.*.subscriber_sex' => ['nullable', Rule::in(['male', 'female', 'other', 'unknown'])],
            'insurances.*.subscriber_phone' => ['nullable', 'string', 'max:30'],
            'insurances.*.copay' => ['nullable', 'numeric', 'min:0'],
            'insurances.*.maximum_bill' => ['nullable', 'numeric', 'min:0'],
            'insurances.*.accept_assignment' => ['sometimes', 'boolean'],
            'insurances.*.effective_date' => ['nullable', 'date'],
            'insurances.*.expiration_date' => ['nullable', 'date'],
            'insurances.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
