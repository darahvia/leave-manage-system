<!DOCTYPE html>
<html>
<head>
    <title>Application for Leave</title>
    @vite(['resources/css/leave.css'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
<div class="header-wrapper">
    <div class="header-container">
        <img src="/images/deped-logo.png" alt="DepEd Logo" class="header-logo">
        <div class="header-text">
            <div class="header-title">
                <span class="dep">Dep</span><span class="ed">Ed</span> Cadiz City
            </div>
            <div class="header-subtitle">Leave Credit System</div>
        </div>
        <img src="/images/deped-cadiz-logo.png" alt="Division Logo" class="header-logo">
    </div>

    <div class="search-bar-section">
        <form method="POST" action="{{ route('employee.find') }}" class="search-form" autocomplete="off">
            @csrf
            <div class="search-box">
                <button type="submit" class="search-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
                <input type="text" name="name" id="employee-search" required placeholder="Find Employee...">
            </div>
        </form>
        <button class="add-employee-btn" id="showAddEmpModal">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <span>Add Employee</span>
        </button>
    </div>
</div>

    <div class="modal-bg" id="addEmpModal">
        <div class="modal-content">
            <button class="close" id="closeAddEmpModal">&times;</button>
            <form method="POST" action="{{ route('employee.add') }}">
                @csrf
                <div class="emp-form">
                    <div class="form-left">
                    <label>Surname:</label>
                    <input type="text" name="surname" required>
                    <label>Given name:</label>
                    <input type="text" name="given_name" required>
                    <label>Middle name:</label>
                    <input type="text" name="middle_name" required>
                    <label>Division:</label>
                    <input type="text" name="division" required>
                    <label>Designation:</label>
                    <input type="text" name="designation" required>
                    </div>

                    <div class="form-right">
                    <label>Original Appointment:</label>
                    <input type="text" name="original_appointment">
                    <label>Salary:</label>
                    <input type="number" step="0.01" name="salary" required>

                    <label>Vacation Leave Forwarded Balance:</label>
                    <input type="number" step="0.01" name="balance_forwarded_vl" required>
                    <label>Sick Leave Forwarded Balance:</label>
                    <input type="number" step="0.01" name="balance_forwarded_sl" required>
                    <button type="submit">Add Employee</button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    @if($employee)
        @php
            $latestApp = $employee->leaveApplications->last();
        @endphp

        <div class="emp-details-table">
            <table class="employee-info-table">
                <tr>
                    <td class="label">SURNAME</td>
                    <td class="value">{{ strtoupper($employee->surname) }}</td>
                    <td class="label">DIVISION</td>
                    <td class="value">{{ strtoupper($employee->division) }}</td>
                    <td class="label">BASIC SALARY</td>
                    <td class="value">{{ number_format($employee->salary, 2) }}</td>
                    <td class="label"> FORCE LEAVE BALANCE </td>
                    <td class="value">{{ strtoupper($employee->fl) }}</td>
                </tr>
                <tr>
                    <td class="label">GIVEN NAME</td>
                    <td class="value">{{ strtoupper($employee->given_name) }}</td>
                    <td class="label">DESIGNATION</td>
                    <td class="value">{{ strtoupper($employee->designation) }}</td>
                    <td class="label">VACATION LEAVE BALANCE</td>
                    <td class="value">{{ $latestApp ? $latestApp->current_vl : ($employee->balance_forwarded_vl ?? 0) }}</td>
                    <td class="label">SPECIAL PRIVILEGE LEAVE BALANCE</td>
                    <td class="value">{{ $employee->spl ?? 0 }}</td>
                </tr>
                <tr>
                    <td class="label">MIDDLE NAME</td>
                    <td class="value">{{ strtoupper($employee->middle_name) }}</td>
                    <td class="label">ORIGINAL APPOINTMENT</td>
                    <td class="value">{{ $employee->original_appointment ?? '' }}</td>
                    <td class="label">SICK LEAVE BALANCE</td>
                    <td class="value">{{ $latestApp ? $latestApp->current_sl : ($employee->balance_forwarded_sl ?? 0) }}</td>
                    <td class="label"> VIEW OTHER LEAVE BALANCES </td>
                    <td class="value">
                        <button type="button" id="viewAllBtn" onclick="showOtherCreditsModal()">View All</button>
                    </td>
                </tr>
            </table>
        </div>

        <div class="modal-bg" id="otherCreditsModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:999;">
            <div class="modal-content" style="background:#fff; margin:5% auto; padding:20px; border-radius:8px; max-width:400px; position:relative;">
                <button class="close" onclick="closeOtherCreditsModal()" style="position:absolute; top:10px; right:10px; background:none; border:none; font-size:20px;">&times;</button>
                <h3>Other Leave Credits</h3>
                <ul style="list-style:none; padding:0;">
                    <li>Special Privilege Leave: <strong>{{ $employee->spl ?? 0 }}</strong></li>
                    <li>Force Leave: <strong>{{ $employee->fl ?? 0 }}</strong></li>
                    <li>Solo Parent Leave: <strong>{{ $employee->solo_parent ?? 0 }}</strong></li>
                    <li>Maternity Leave: <strong>{{ $employee->ml ?? 0 }}</strong></li>
                    <li>Paternity Leave: <strong>{{ $employee->pl ?? 0 }}</strong></li>
                    <li>RA 9710 Leave: <strong>{{ $employee->ra9710 ?? 0 }}</strong></li>
                    <li>Rehabilitation Leave: <strong>{{ $employee->rl ?? 0 }}</strong></li>
                    <li>Special Emergency Leave: <strong>{{ $employee->sel ?? 0 }}</strong></li>
                    <li>Study Leave: <strong>{{ $employee->study_leave ?? 0 }}</strong></li>
                </ul>
            </div>
        </div>

    @endif


    @if($employee)
        <table class="leave-table">
            <thead>
                <tr>
                    <th>PERIOD</th>
                    <th>VL EARNED</th>
                    <th>SL EARNED</th>
                    <th>DATE LEAVE FILED</th>
                    <th>DATE LEAVE INCURRED</th>
                    <th>LEAVE INCURRED</th>
                    <th>VL</th>
                    <th>SL</th>
                    <th>SPL</th>
                    <th>FL</th>
                    <th>SOLO PARENT</th>
                    <th>OTHERS</th>
                    <th>REMARKS</th>
                    <th>VL BALANCE</th>
                    <th>SL BALANCE</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-label="PERIOD">BALANCE FORWARDED (from last year)</td>
                    <td data-label="VL EARNED"></td>
                    <td data-label="SL EARNED"></td>
                    <td data-label="DATE LEAVE FILED"></td>
                    <td data-label="DATE LEAVE INCURRED"></td>
                    <td data-label="LEAVE INCURRED"></td>
                    <td data-label="VL"></td>
                    <td data-label="SL"></td>
                    <td data-label="SPL"></td>
                    <td data-label="FL"></td>
                    <td data-label="SOLO PARENT"></td>
                    <td data-label="OTHERS"></td>
                    <td data-label="REMARKS"></td>
                    <td data-label="VL BALANCE">{{ number_format($employee->balance_forwarded_vl, 2) }}</td>
                    <td data-label="SL BALANCE">{{ number_format($employee->balance_forwarded_sl, 2) }}</td>
                    <td data-label="ACTIONS"></td>
                </tr>
                @if($employee->leaveApplications && $employee->leaveApplications->count())
                @foreach($employee->leaveApplications->sortBy([
                    fn($a, $b) => ($a->earned_date ?? $a->date_filed) <=> ($b->earned_date ?? $b->date_filed),
                    'date_filed'
                ]) as $app)
                        <tr>
                            <td data-label="PERIOD">{{ $app->earned_date ? \Carbon\Carbon::parse($app->earned_date)->format('F j, Y') : '' }}</td>
                            <td data-label="VL EARNED">
                                @if($app->is_credit_earned)
                                    @if($app->leave_type === 'VL' || !$app->leave_type)
                                        {{ $app->earned_vl ?? '1.25' }}
                                    @endif
                                @endif
                            </td>
                            <td data-label="SL EARNED">
                                @if($app->is_credit_earned)
                                    @if($app->leave_type === 'SL' || !$app->leave_type)
                                        {{ $app->earned_sl ?? '1.25' }}
                                    @endif
                                @endif
                            </td>
                            <td data-label="DATE LEAVE FILED">{{ $app->date_filed ? \Carbon\Carbon::parse($app->date_filed)->format('F j, Y') : '' }}</td>
                            <td data-label="DATE LEAVE INCURRED">
                                @if($app->inclusive_date_start && $app->inclusive_date_end)
                                    {{ \Carbon\Carbon::parse($app->inclusive_date_start)->format('F j, Y') }} - {{ \Carbon\Carbon::parse($app->inclusive_date_end)->format('F j, Y') }}
                                @elseif($app->date_incurred)
                                    {{ \Carbon\Carbon::parse($app->date_incurred)->format('F j, Y') }}
                                @endif
                            </td>
                            <td data-label="LEAVE INCURRED">
                                @if(!$app->is_credit_earned)
                                    {{ $app->leave_type ?? '' }}
                                @endif
                            </td>
                            <td data-label="VL">
                                @if(!$app->is_credit_earned && $app->leave_type === 'VL')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td data-label="SL">
                                @if(!$app->is_credit_earned && $app->leave_type === 'SL')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td data-label="SPL">
                                @if(!$app->is_credit_earned && $app->leave_type === 'SPL')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td data-label="FL">
                                @if(!$app->is_credit_earned && $app->leave_type === 'FL')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td data-label="SOLO PARENT">
                                @if(!$app->is_credit_earned && $app->leave_type === 'SOLO PARENT')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td data-label="OTHERS">
                                @if(!$app->is_credit_earned && $app->leave_type === 'OTHERS')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td data-label="REMARKS"></td>
                            <td data-label="VL BALANCE">{{ $app->current_vl ?? '' }}</td>
                            <td data-label="SL BALANCE">{{ $app->current_sl ?? '' }}</td>
                            <td data-label="ACTIONS">
                                @if(!$app->is_cancellation)
                                <button type="button" class="delete-btn" onclick="deleteRecord({{ $app->id }}, '{{ $app->is_credit_earned ? 'credit' : 'leave' }}')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3,6 5,6 21,6"></polyline>
                                        <path d="m5,6 1,14c0,1 1,2 2,2h8c1,0 2-1 2-2l1-14"></path>
                                        <path d="m10,11 0,6"></path>
                                        <path d="m14,11 0,6"></path>
                                        <path d="M8,6V4c0-1,1-2,2-2h4c0-1,1-2,2-2v2"></path>
                                    </svg>
                                </button>
                                @if(!$app->is_credit_earned)
                                <button type="button" class="edit-btn" onclick="editLeaveApplication(
                                    {{ $app->id }},
                                    '{{ $app->leave_type }}',
                                    '{{ \Carbon\Carbon::parse($app->date_filed)->format('Y-m-d') }}',
                                    '{{ \Carbon\Carbon::parse($app->inclusive_date_start)->format('Y-m-d') }}',
                                    '{{ \Carbon\Carbon::parse($app->inclusive_date_end)->format('Y-m-d') }}',
                                    '{{ $app->working_days }}'
                                    )">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M12 12l7-7 3 3-7 7-3 0 0-3z"></path>
                                        </svg>
                                    </button>
                                <button type="button" class="cancel-btn" onclick="cancelLeaveApplication(
                                    {{ $app->id }},
                                    '{{ $app->leave_type }}',
                                    '{{ \Carbon\Carbon::parse($app->inclusive_date_start)->format('Y-m-d') }}',
                                    '{{ \Carbon\Carbon::parse($app->inclusive_date_end)->format('Y-m-d') }}',
                                    '{{ $app->working_days }}'
                                    )">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="15" y1="9" x2="9" y2="15"></line>
                                            <line x1="9" y1="9" x2="15" y2="15"></line>
                                        </svg>
                                    </button>
                                @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    @endif


    @if($employee)
        <div class="bottom-section">
            <form method="POST" action="{{ route('leave.submit') }}" id="leave-form" class="leave-form">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <input type="hidden" name="edit_id" id="edit_id" value="">
                <input type="hidden" name="_method" id="form_method" value="POST">
                   <input type="hidden" name="is_cancellation" id="is_cancellation" value="0">
                <div class="emp-form" id="leave-form-container">
                    <label>Leave Type:</label>
                    <select name="leave_type" id="leave_type" required>
                        <option value="VL">Vacation Leave</option>
                        <option value="SL">Sick Leave</option>
                        <option value="ML">Maternity Leave</option>
                        <option value="PL">Paternity Leave</option>
                        <option value="SPL">Special Leave</option>
                        <option value="FL">Force Leave</option>
                        <option value="SOLO PARENT">Solo Parent</option>
                        <option value="OTHERS">Others</option>
                    </select>
                    <label>Date Filed:</label>
                    <input type="date" name="date_filed" id="date_filed" required>
                    <label>Leave Start Date (Inclusive):</label>
                    <input type="date" name="inclusive_date_start" id="inclusive_date_start" required>
                    <label>Leave End Date (Inclusive):</label>
                    <input type="date" name="inclusive_date_end" id="inclusive_date_end" required>
                    <label>Working Days:</label>
                    <input type="number" name="working_days" id="working_days" readonly style="background-color: #f5f5f5;">
                    <button type="submit" id="submit-btn">Add Leave</button>
                    <button type="button" id="cancel-edit-btn" onclick="cancelEdit()" style="display: none; margin-left: 10px; background-color: #6c757d;">Cancel</button>
                </div>
            </form>
            <form method="POST" action="{{ route('leave.credits') }}">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="emp-form">
                    <label>Earned Date:</label>
                    <input type="date" name="earned_date" required>
                    <button type="submit">Add Credits Earned</button>
                </div>
            </form>
            <form method="POST" action="{{ route('leave.otherCredits') }}">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="emp-form">
                    <label>Leave Type:</label>
                    <select name="leave_type" required>
                        <option value="spl">Special Privilege Leave</option>
                        <option value="fl">Forced Leave</option>
                        <option value="solo_parent">Solo Parent Leave</option>
                        <option value="ml">Maternity Leave</option>
                        <option value="pl">Paternity Leave</option>
                        <option value="ra9710">RA9710 Leave</option>
                        <option value="rl">Rehabilitation Leave</option>
                        <option value="sel">Special Emergency Leave</option>
                        <option value="study_leave">Study Leave</option>
                    </select>

                    <label>Credits to Add:</label>
                    <input type="number" name="credits" step="0.01" required>

                    <button type="submit">Add Other Credits</button>
                </div>
            </form>
        </div>
    @endif

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Make Laravel routes available to JavaScript
        window.autocompleteRoute = '{{ route("employee.autocomplete") }}';
        window.leaveUpdateRoute = '{{ route("leave.update") }}';
        window.deleteRoute = '{{ route("leave.delete") }}'; // Add this line
        window.csrfToken = '{{ csrf_token() }}'; // Add this line
    </script>
    
    @vite(['resources/js/leave-form.js'])
</body>
</html>