@extends('layouts.admin')

@section('content')
<div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
    <div><div class="page-kicker mb-1">Business snapshot</div><h1 class="fw-bold mb-1" style="font-family:Manrope,sans-serif;letter-spacing:-.045em">Good day, admin.</h1><p class="text-muted mb-0">Here is a live overview of your catering operations.</p></div>
    <a class="btn luxury-btn px-3" href="{{ route('admin.reservations') }}">Review reservations</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><a class="stat-card stat-card-link" href="{{ route('admin.reservations') }}"><span class="badge-soft">Bookings</span><h3>{{ $reservationCount }}</h3><p class="mb-0 text-muted">Total reservation requests</p></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card stat-card-link" href="{{ route('admin.inquiries') }}"><span class="badge-soft">Inbox</span><h3>{{ $inquiryCount }}</h3><p class="mb-0 text-muted">Customer inquiries received</p></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card stat-card-link" href="{{ route('admin.services.index') }}"><span class="badge-soft">Services</span><h3>{{ $serviceCount }}</h3><p class="mb-0 text-muted">Active service offerings</p></a></div>
    <div class="col-sm-6 col-xl-3"><a class="stat-card stat-card-link" href="{{ session('admin_role') === 'full' ? route('admin.packages.index') : route('packages') }}"><span class="badge-soft">Packages</span><h3>{{ $packageCount }}</h3><p class="mb-0 text-muted">Published catering packages</p></a></div>
</div>
<div class="row g-4">
    <div class="col-lg-7"><div class="card p-4 h-100"><div class="d-flex align-items-center justify-content-between mb-4"><div><h5 class="fw-bold mb-1">Priority workspace</h5><p class="text-muted small mb-0">Keep client communication and booking decisions moving.</p></div><span class="badge-soft">Today</span></div><div class="workflow-item"><div class="workflow-icon">01</div><div><strong>Review reservation requests</strong><p class="text-muted mb-0">Confirm availability, update status, and respond to event needs.</p></div><a href="{{ route('admin.reservations') }}">Open</a></div><div class="workflow-item"><div class="workflow-icon">02</div><div><strong>Reply to inquiries</strong><p class="text-muted mb-0">Give prospective clients a timely, helpful response.</p></div><a href="{{ route('admin.inquiries') }}">Open</a></div>@if(session('admin_role') === 'full')<div class="workflow-item"><div class="workflow-icon">03</div><div><strong>Keep packages current</strong><p class="text-muted mb-0">Update inclusions, pricing, and featured offerings.</p></div><a href="{{ route('admin.packages.index') }}">Manage</a></div>@endif</div></div>
    <div class="col-lg-5"><div class="card p-4 h-100"><h5 class="fw-bold mb-1">Quick actions</h5><p class="text-muted small mb-4">Frequently used management tools.</p><div class="d-grid gap-2"><a class="quick-link" href="{{ route('admin.inquiries') }}"><span>Client inquiries</span><b>→</b></a><a class="quick-link" href="{{ route('admin.reservations') }}"><span>Reservations</span><b>→</b></a>@if(session('admin_role') === 'full')<a class="quick-link" href="{{ route('admin.packages.index') }}"><span>Package editor</span><b>→</b></a><a class="quick-link" href="{{ route('admin.analytics') }}"><span>Business analytics</span><b>→</b></a>@endif</div></div></div>
</div>
<section class="calendar-card card p-4 mt-4" id="reservation-calendar" aria-labelledby="reservation-calendar-title">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <h5 class="fw-bold mb-1" id="reservation-calendar-title">Reservation calendar</h5>
            <p class="text-muted small mb-0">Accepted, completed, and cancelled events by date.</p>
        </div>
        <div class="calendar-legend" aria-label="Reservation status legend">
            <span><i class="calendar-dot calendar-dot--confirmed"></i>Accepted</span>
            <span><i class="calendar-dot calendar-dot--completed"></i>Completed</span>
            <span><i class="calendar-dot calendar-dot--cancelled"></i>Cancelled</span>
        </div>
    </div>
    <div class="calendar-toolbar">
        <button type="button" class="calendar-nav" id="calendarPrevious" aria-label="Previous month">&#8592;</button>
        <h6 id="calendarMonth" class="mb-0 fw-bold" aria-live="polite"></h6>
        <button type="button" class="calendar-nav" id="calendarNext" aria-label="Next month">&#8594;</button>
    </div>
    <div class="calendar-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
    <div class="calendar-grid" id="reservationCalendar" aria-label="Calendar days"></div>
    <div class="calendar-events" id="calendarEvents" aria-live="polite"></div>
</section>
<div class="card p-4 mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        </div>
        </div>
        <span class="badge-soft">{{ count($sidebarCalendar['bookings']) }} days</span>
    </div>

    <div class="calendar-weekdays" aria-label="Calendar weekdays">
        <span>Sun</span>
        <span>Mon</span>
        <span>Tue</span>
        <span>Wed</span>
        <span>Thu</span>
        <span>Fri</span>
        <span>Sat</span>
    </div>

    <div class="calendar-grid" aria-live="polite">
        @foreach($sidebarCalendar['days'] as $day)
            <a href="{{ route('admin.reservations', ['date_from' => $day['date'], 'date_to' => $day['date']]) }}" class="calendar-day-link {{ $day['isCurrentMonth'] ? 'calendar-day--current' : 'calendar-day--muted' }} {{ $day['isSelected'] ? 'calendar-day--selected' : '' }}" title="Open bookings for {{ \Carbon\Carbon::parse($day['date'])->format('M j, Y') }}">
                <div class="calendar-day {{ $day['isCurrentMonth'] ? 'calendar-day--current' : 'calendar-day--muted' }} {{ $day['isSelected'] ? 'calendar-day--selected' : '' }}">
                    <span class="calendar-day-number">{{ $day['day'] }}</span>
                    @if(!empty($day['bookings']))
                        @foreach(array_slice($day['bookings'], 0, 2) as $customer)
                            <span class="calendar-booking">{{ \Illuminate\Support\Str::limit($customer, 14) }}</span>
                        @endforeach
                        @if(count($day['bookings']) > 2)
                            <span class="calendar-more">+{{ count($day['bookings']) - 2 }} more</span>
                        @endif
                    @endif
                </div>
            </a>
        @endforeach
    </div>
</div>

@if($selectedDate)
    <div class="card p-4 mt-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="fw-bold mb-1">Bookings for {{ \Carbon\Carbon::parse($selectedDate)->format('M j, Y') }}</h5>
                <p class="text-muted small mb-0">Jump into the reservations list for this date.</p>
            </div>
            <a class="btn luxury-btn btn-sm" href="{{ route('admin.reservations', ['date_from' => $selectedDate, 'date_to' => $selectedDate]) }}">Open list</a>
        </div>

        @if($selectedReservations->isEmpty())
            <p class="text-muted mb-0">No reservations booked on this date.</p>
        @else
            <div class="d-grid gap-2">
                @foreach($selectedReservations as $reservation)
                    <a class="selected-day-booking" href="{{ route('admin.reservations', ['search' => $reservation->reservation_code]) }}">
                        <div>
                            <strong>{{ $reservation->full_name }}</strong>
                            <div class="small text-muted">{{ $reservation->event_type }} • {{ $reservation->event_time }}</div>
                        </div>
                        <span class="status-badge status-badge--{{ $reservation->status }}">{{ $reservation->status }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif
<style>
.workflow-item{display:flex;align-items:center;gap:1rem;padding:1rem 0;border-top:1px solid var(--line)}
.workflow-item:first-of-type{border-top:0}
.workflow-icon{width:35px;height:35px;display:grid;place-items:center;border-radius:9px;background:var(--mint);color:var(--teal-dark);font-size:.7rem;font-weight:800}
.workflow-item strong{font-size:.9rem}
.workflow-item p{font-size:.8rem;margin-top:.15rem}
.workflow-item a{margin-left:auto;color:var(--teal-dark);font-weight:800;text-decoration:none;font-size:.8rem}
.quick-link{display:flex;justify-content:space-between;align-items:center;padding:.9rem 1rem;border:1px solid var(--line);border-radius:9px;color:var(--ink);font-weight:700;text-decoration:none;transition:.18s}
.quick-link:hover{border-color:#9bd5cf;background:var(--mint);color:var(--teal-dark)}
.quick-link b{font-size:1.1rem;color:var(--teal)}
.stat-card-link{display:block;color:inherit;text-decoration:none}.stat-card-link:hover{color:inherit}
.calendar-card{overflow:visible}
.calendar-legend{display:flex;align-items:center;flex-wrap:wrap;gap:.9rem;color:var(--muted);font-size:.72rem;font-weight:700}
.calendar-legend span{display:inline-flex;align-items:center;gap:.35rem}
.calendar-dot{width:9px;height:9px;border-radius:50%;background:var(--teal)}
.calendar-dot--confirmed{background:#0d8b83}.calendar-dot--completed{background:#4d77b8}.calendar-dot--cancelled{background:#c54545}
.calendar-events{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem;margin-top:1rem}
.calendar-event-detail{display:grid;gap:.25rem;padding:.7rem .8rem;border:1px solid var(--line);border-left:3px solid #0d8b83;border-radius:8px;background:var(--surface);font-size:.75rem;overflow-wrap:anywhere}
.calendar-event-detail--completed{border-left-color:#4d77b8}.calendar-event-detail--cancelled{border-left-color:#c54545}
.calendar-event-detail strong{font-size:.78rem}.calendar-event-detail small{color:var(--muted);line-height:1.35}
.calendar-day:focus-within{outline:2px solid #71c9c0;outline-offset:1px}
body.dark-mode .calendar-day{background:#12202e}
body.dark-mode .calendar-event--cancelled{background:#3b252b}
@media(max-width:992px){.calendar-events{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:575px){.calendar-events{grid-template-columns:1fr}.calendar-legend{gap:.5rem}}
.calendar-toolbar{display:flex;align-items:center;justify-content:center;gap:1.25rem;margin-bottom:1rem}.calendar-nav{width:34px;height:34px;border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--teal-dark);font-size:1.1rem;line-height:1}.calendar-nav:hover{background:var(--mint)}.calendar-weekdays,.calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px}.calendar-weekdays{color:var(--muted);font-size:.68rem;font-weight:800;letter-spacing:.08em;text-align:center;text-transform:uppercase;margin-bottom:6px}.calendar-day{position:relative;min-height:92px;padding:.55rem;background:#fbfcfd;border:1px solid var(--line);border-radius:8px}.calendar-day--empty{background:transparent;border-color:transparent}.calendar-day--today{border-color:#71c9c0;box-shadow:inset 0 0 0 1px #71c9c0}.calendar-day-number{font-size:.78rem;font-weight:800}.calendar-event{display:block;width:100%;margin-top:.45rem;padding:.28rem .35rem;border:0;border-left:3px solid;border-radius:4px;background:var(--mint);color:var(--ink);font-size:.68rem;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.calendar-event--confirmed{border-color:#0d8b83}.calendar-event--completed{border-color:#4d77b8}.calendar-event--cancelled{border-color:#c54545;background:#fff2f2}.calendar-hover-card{position:absolute;z-index:20;top:calc(100% + 6px);left:0;width:240px;padding:.7rem;background:var(--surface);border:1px solid var(--line);border-radius:8px;box-shadow:0 12px 28px rgba(21,37,55,.18);opacity:0;pointer-events:none;transform:translateY(-4px);transition:opacity .15s ease,transform .15s ease}.calendar-day:hover .calendar-hover-card,.calendar-day:focus-within .calendar-hover-card{opacity:1;transform:translateY(0)}.calendar-hover-item{padding:.35rem 0;border-top:1px solid var(--line);font-size:.7rem}.calendar-hover-item:first-child{padding-top:0;border-top:0}.calendar-hover-item strong{display:block}.calendar-hover-item small{display:block;color:var(--muted);margin-top:.12rem}.calendar-legend{display:flex;flex-wrap:wrap;gap:.8rem;color:var(--muted);font-size:.72rem;font-weight:700}.calendar-legend span{display:inline-flex;align-items:center;gap:.35rem}.calendar-dot{width:8px;height:8px;border-radius:50%;display:inline-block}.calendar-dot--confirmed{background:#0d8b83}.calendar-dot--completed{background:#4d77b8}.calendar-dot--cancelled{background:#c54545}.calendar-events{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-top:1rem}.calendar-event-detail{padding:.75rem;border:1px solid var(--line);border-radius:8px;background:var(--surface)}.calendar-event-detail strong{display:block;font-size:.8rem}.calendar-event-detail small{display:block;color:var(--muted);margin-top:.15rem}.calendar-event-detail--cancelled{border-left:3px solid #c54545}.calendar-event-detail--confirmed{border-left:3px solid #0d8b83}.calendar-event-detail--completed{border-left:3px solid #4d77b8}
body.dark-mode .calendar-day{background:#12202e}.calendar-event--cancelled{background:#3b252b}.calendar-event-detail{background:var(--surface)}
.calendar-weekdays{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:.45rem;margin-top:.5rem;margin-bottom:.55rem;color:#607787;font-size:.7rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.calendar-weekdays span{text-align:center}
.calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:.45rem}
.calendar-day-link{display:block;text-decoration:none;color:inherit}
.calendar-day{min-height:95px;padding:.5rem .45rem;border:1px solid var(--line);border-radius:10px;background:#f9fbfc;display:flex;flex-direction:column;gap:.2rem;align-items:flex-start;transition:.18s ease}
.calendar-day:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(12,31,46,.08)}
.calendar-day--muted{opacity:.62;background:#f5f7f9}
.calendar-day--current{background:#fff}
.calendar-day--selected{outline:2px solid #0d8b83;outline-offset:1px;background:#ecf9f7}
.calendar-day-number{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;font-size:.72rem;font-weight:800;color:#30495b;background:#edf4f6}
.calendar-booking{display:inline-block;max-width:100%;padding:.15rem .35rem;border-radius:999px;background:#dff5f2;color:#0b7d74;font-size:.62rem;font-weight:700;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.calendar-more{font-size:.62rem;color:#607787;font-weight:700}
.selected-day-booking{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.75rem .9rem;border:1px solid var(--line);border-radius:10px;background:#f9fbfc;color:var(--ink);text-decoration:none;transition:.18s ease}
.selected-day-booking:hover{border-color:#9bd5cf;background:var(--mint);text-decoration:none}
.status-badge{display:inline-flex;align-items:center;padding:.28rem .55rem;border-radius:999px;font-size:.65rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
.status-badge--pending{background:#fff1c8;color:#8a6613}
.status-badge--confirmed{background:#dff5f2;color:#0b7d74}
.status-badge--completed{background:#e6f4ea;color:#2d6b4d}
.status-badge--cancelled{background:#fde5e5;color:#b63b3b}
body.dark-mode .calendar-weekdays{color:#9bb0c2}
body.dark-mode .calendar-day{background:#1a2d3f;border-color:#2d4257}
body.dark-mode .calendar-day:hover{box-shadow:0 8px 20px rgba(0,0,0,.18)}
body.dark-mode .calendar-day--muted{background:#132536;opacity:.8}
body.dark-mode .calendar-day--current{background:#172a3b}
body.dark-mode .calendar-day--selected{background:#123b42;outline-color:#75d8cf}
body.dark-mode .calendar-day-number{background:#233d50;color:#e6f3ff}
body.dark-mode .calendar-booking{background:#164d4b;color:#dffaf7}
body.dark-mode .calendar-more{color:#c5d6e6}
body.dark-mode .selected-day-booking{background:#1a2d3f;border-color:#2d4257;color:#edf5fb}
body.dark-mode .selected-day-booking:hover{background:#173844}
body.dark-mode .status-badge--pending{background:#56461b;color:#fbe8a1}
body.dark-mode .status-badge--confirmed{background:#123b42;color:#dcfffb}
body.dark-mode .status-badge--completed{background:#173b2d;color:#d8f8e2}
body.dark-mode .status-badge--cancelled{background:#4a2325;color:#ffdede}

@media(max-width:992px){
    .row.g-4 > [class*="col-"]{margin-bottom:1rem}
    .calendar-events{grid-template-columns:repeat(2,minmax(0,1fr))}
}

@media(max-width:768px){
    .workflow-item{gap:.75rem;padding:.75rem 0}
    .workflow-item strong{font-size:.85rem}
    .workflow-item p{font-size:.75rem}
    .workflow-item a{margin-left:0;margin-top:.5rem;font-size:.75rem}
    .quick-link{padding:.75rem;font-size:.9rem}
    .quick-link b{font-size:1rem}
    .calendar-day{min-height:72px;padding:.35rem}.calendar-event{font-size:.6rem;padding:.2rem}.calendar-events{grid-template-columns:1fr}.calendar-legend{gap:.5rem}
    .calendar-day{min-height:80px;padding:.45rem .3rem}
}

@media(max-width:575px){
    .workflow-item{align-items:flex-start;flex-direction:column}
    .workflow-item a{width:100%;text-align:center;padding:.4rem;margin-left:0;margin-top:.5rem}
    .quick-link{flex-direction:column;align-items:flex-start;padding:.6rem}
    .quick-link b{align-self:flex-end;margin-top:.4rem;font-size:1rem}
    .quick-link span{width:100%}
    .calendar-weekdays{font-size:.58rem}.calendar-weekdays,.calendar-grid{gap:3px}.calendar-day{min-height:58px;padding:.25rem}.calendar-day-number{font-size:.68rem}.calendar-event{height:5px;margin-top:.3rem;padding:0;border-left-width:0;font-size:0}.calendar-event--cancelled{background:#c54545}.calendar-event--completed{background:#4d77b8}.calendar-event--confirmed{background:#0d8b83}
    .calendar-weekdays{font-size:.6rem;gap:.3rem}
    .calendar-day{min-height:72px;padding:.35rem .25rem}
    .calendar-booking{font-size:.55rem}
}
</style>
<script>
(() => {
    const reservations = @json($calendarEvents);
    const calendar = document.getElementById('reservationCalendar');
    const monthLabel = document.getElementById('calendarMonth');
    const eventsPanel = document.getElementById('calendarEvents');
    if (!calendar || !monthLabel || !eventsPanel) return;

    const today = new Date();
    let displayedMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const formatter = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' });
    const statusLabels = { confirmed: 'Accepted', completed: 'Completed', cancelled: 'Cancelled' };
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
    const eventsByDate = reservations.reduce((events, reservation) => {
        (events[reservation.date] ??= []).push(reservation);
        return events;
    }, {});

    const renderMonthEvents = () => {
        const monthPrefix = `${displayedMonth.getFullYear()}-${String(displayedMonth.getMonth() + 1).padStart(2, '0')}`;
        const monthEvents = reservations.filter((reservation) => reservation.date.startsWith(monthPrefix));
        eventsPanel.innerHTML = monthEvents.length
            ? monthEvents.map((event) => `<article class="calendar-event-detail calendar-event-detail--${event.status}"><strong>${escapeHtml(event.eventType)} · ${statusLabels[event.status]}</strong><small>${escapeHtml(event.date)} · ${escapeHtml(event.time)}</small><small>${escapeHtml(event.name)} · ${escapeHtml(event.venue)}</small></article>`).join('')
            : '<p class="text-muted small mb-0">No accepted, completed, or cancelled reservations this month.</p>';
    };

    const renderCalendar = () => {
        const year = displayedMonth.getFullYear();
        const month = displayedMonth.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const todayKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        monthLabel.textContent = formatter.format(displayedMonth);
        calendar.replaceChildren();

        for (let index = 0; index < firstDay; index += 1) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day calendar-day--empty';
            emptyDay.setAttribute('aria-hidden', 'true');
            calendar.append(emptyDay);
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayEvents = eventsByDate[date] ?? [];
            const dayElement = document.createElement('div');
            dayElement.className = `calendar-day${date === todayKey ? ' calendar-day--today' : ''}`;
            const dayNumber = document.createElement('div');
            dayNumber.className = 'calendar-day-number';
            dayNumber.textContent = String(day);
            dayElement.append(dayNumber);

            dayEvents.forEach((event) => {
                const eventButton = document.createElement('button');
                eventButton.type = 'button';
                eventButton.className = `calendar-event calendar-event--${event.status}`;
                eventButton.textContent = event.eventType;
                eventButton.setAttribute('aria-label', `${event.eventType}, ${statusLabels[event.status]}, ${event.time}, ${event.name}, ${event.venue}`);
                dayElement.append(eventButton);
            });

            if (dayEvents.length) {
                const hoverCard = document.createElement('div');
                hoverCard.className = 'calendar-hover-card';
                hoverCard.innerHTML = dayEvents.map((event) => `<div class="calendar-hover-item"><strong>${escapeHtml(event.eventType)} · ${statusLabels[event.status]}</strong><small>${escapeHtml(event.time)} · ${escapeHtml(event.name)}</small><small>${escapeHtml(event.venue)}</small></div>`).join('');
                dayElement.append(hoverCard);
            }

            calendar.append(dayElement);
        }

        renderMonthEvents();
    };

    document.getElementById('calendarPrevious')?.addEventListener('click', () => {
        displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() - 1, 1);
        renderCalendar();
    });
    document.getElementById('calendarNext')?.addEventListener('click', () => {
        displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() + 1, 1);
        renderCalendar();
    });
    renderCalendar();
})();
</script>
@endsection
