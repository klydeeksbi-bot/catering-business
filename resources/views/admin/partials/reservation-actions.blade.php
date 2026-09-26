<div class="reservation-actions">
    @php($paymentTotal = $reservation->total_cost ?? $reservation->estimated_budget)
    @php($outstandingBalance = $paymentTotal === null ? null : max(0, (float) $paymentTotal - (float) ($reservation->amount_paid ?? 0)))
    @if($reservation->status === 'pending')
        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button class="btn btn-sm btn-success quick-action" type="submit">Accept</button></form>
        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-danger quick-action" type="submit">Cancel</button></form>
    @endif
    <div class="reservation-action-group">
        <span class="reservation-action-label">Status</span>
        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-status>@csrf @method('PATCH')
            <select name="status" class="form-select form-select-sm status-select status-select--{{ $reservation->status }}">
                <option value="pending" @selected($reservation->status === 'pending')>Pending</option>
                <option value="confirmed" @selected($reservation->status === 'confirmed')>Accepted</option>
                <option value="completed" @selected($reservation->status === 'completed')>Completed</option>
                <option value="cancelled" @selected($reservation->status === 'cancelled')>Cancelled</option>
            </select>
            <button class="btn btn-sm luxury-btn" type="submit">Save</button>
        </form>
    </div>
    @if($reservation->status === 'confirmed')
        <div class="reservation-action-group">
            <span class="reservation-action-label">Schedule & package</span>
            <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Update this reservation's schedule and package?">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="confirmed">
                <select name="package_id" class="form-select form-select-sm">
                    @foreach($packages as $package)
                        <option value="{{ $package->id }}" @selected($reservation->package_id === $package->id)>{{ $package->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="event_date" value="{{ $reservation->event_date }}" class="form-control form-control-sm" required>
                <input type="time" name="event_time" value="{{ $reservation->event_time }}" class="form-control form-control-sm" required>
                <button class="btn btn-sm luxury-btn" type="submit">Save</button>
            </form>
        </div>
    @endif
    <div class="reservation-action-group">
        <span class="reservation-action-label">Contract price</span>
        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Update the contract price for this reservation?">@csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $reservation->status }}">
            <input type="number" name="total_cost" min="0" step="1" value="{{ old('total_cost', $reservation->total_cost) }}" class="form-control form-control-sm" placeholder="Contract price" required>
            <button class="btn btn-sm luxury-btn" type="submit">Save</button>
        </form>
    </div>
    <div class="reservation-action-group">
        <span class="reservation-action-label">Payment</span>
        <form method="POST" action="{{ route('admin.reservations.status', $reservation) }}" data-confirm-message="Update payment details for this reservation?">@csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $reservation->status }}">
            <label class="payment-field-label">Total</label>
            <input type="number" name="estimated_budget" min="0" step="1" value="{{ old('estimated_budget', (int) ($reservation->estimated_budget ?? 0)) }}" class="form-control form-control-sm" placeholder="0">
            <label class="payment-field-label">Down payment</label>
            <input type="number" name="amount_paid" min="0" step="1" value="{{ old('amount_paid', (int) ($reservation->amount_paid ?? 0)) }}" class="form-control form-control-sm" placeholder="0">
            <div class="payment-actions-inline">
                <button class="btn btn-sm luxury-btn" type="submit">Save</button>
                <button class="btn btn-sm btn-success" type="submit" name="mark_fully_paid" value="1">Fully paid</button>
            </div>
        </form>
        @if($outstandingBalance > 0)
            <div class="payment-warning" role="alert">Unpaid balance: &#8369;{{ number_format($outstandingBalance, 2) }}</div>
        @endif
    </div>
</div>
