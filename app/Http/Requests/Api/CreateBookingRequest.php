<?php

namespace App\Http\Requests\Api;

use Carbon\Carbon;
use Illuminate\Validation\Validator;

class CreateBookingRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'booking_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'booking_time' => 'required|date_format:H:i',
            'booking_slot' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['booking_date', 'booking_time'])) {
                return;
            }

            $bookingDateTime = Carbon::createFromFormat(
                '!Y-m-d H:i',
                $this->string('booking_date').' '.$this->string('booking_time'),
                config('app.timezone')
            );

            if ($bookingDateTime->lessThanOrEqualTo(now())) {
                $validator->errors()->add('booking_time', 'يجب أن يكون وقت الحجز في المستقبل.');
            }
        });
    }
}
