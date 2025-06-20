{{-- filepath: resources/views/leave/index.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Application for Leave</title>
    @vite(['resources/css/leave.css'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="error">{{ session('error') }}</div>
    @endif


    <!-- Top: Search and Add Employee Icon -->
    <div class="top-section">
        <form method="POST" action="{{ route('employee.find') }}" class="search-form" autocomplete="off">
            @csrf
            <div class="emp-form">
                <input type="text" name="name" id="employee-search" autocomplete="off" required>
                <div id="suggestions"></div>
                <button type="submit" class="search-btn">Search</button>
            </div>
        </form>
        <button class="icon-btn-square" id="showAddEmpModal" title="Add Employee">
            &#43; <!-- plus icon -->
        </button>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal-bg" id="addEmpModal">
        <div class="modal-content">
            <button class="close" id="closeAddEmpModal">&times;</button>
            <form method="POST" action="{{ route('employee.add') }}">
                @csrf
                <div class="emp-form">
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
            </form>
        </div>
    </div>


    <!-- Employee Details Table -->
    @if($employee)

        <div class="emp-details-table">
            <table class="employee-info-table">
                <tr>
                    <td class="label">SURNAME</td>
                    <td class="value">{{ strtoupper($employee->surname) }}</td>
                    <td class="label">DIVISION</td>
                    <td class="value">{{ strtoupper($employee->division) }}</td>
                    <td class="label">BASIC SALARY</td>
                    <td class="value">{{ number_format($employee->salary, 2) }}</td>
                    <td class="label"></td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">GIVEN NAME</td>
                    <td class="value">{{ strtoupper($employee->given_name) }}</td>
                    <td class="label">DESIGNATION</td>
                    <td class="value">{{ strtoupper($employee->designation) }}</td>
                    <td class="label">FORCED LEAVE BALANCE</td>
                    <td class="value">{{ $employee->fl ?? 0 }}</td>
                    <td class="label"></td>
                    <td class="value"></td>
                </tr>
                <tr>
                    <td class="label">MIDDLE NAME</td>
                    <td class="value">{{ strtoupper($employee->middle_name) }}</td>
                    <td class="label">ORIGINAL APPOINTMENT</td>
                    <td class="value">{{ $employee->original_appointment ?? '' }}</td>
                    <td class="label">SPECIAL PRIVILEGE LEAVE BALANCE</td>
                    <td class="value">{{ $employee->spl ?? 0 }}</td>
                    <td class="label"></td>
                    <td class="value"></td>
                </tr>
            </table>
        </div>
    @endif


    <!-- Leave Records Table -->
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
                <!-- BALANCE FORWARDED -->
                <tr>
                    <td>BALANCE FORWARDED (from last year)</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>{{ number_format($employee->balance_forwarded_vl, 2) }}</td>
                    <td>{{ number_format($employee->balance_forwarded_sl, 2) }}</td>
                    <td></td>
                </tr>
                @if($employee->leaveApplications && $employee->leaveApplications->count())
                    @foreach($employee->leaveApplications as $app)
                        <tr>
                            <td>{{ $app->earned_date ? \Carbon\Carbon::parse($app->earned_date)->format('F j, Y') : '' }}</td>
                            <td>
                                @if($app->is_credit_earned)
                                    @if($app->leave_type === 'VL' || !$app->leave_type)
                                        {{ $app->earned_vl ?? '1.25' }}
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if($app->is_credit_earned)
                                    @if($app->leave_type === 'SL' || !$app->leave_type)
                                        {{ $app->earned_sl ?? '1.25' }}
                                    @endif
                                @endif
                            </td>
                            <td>{{ $app->date_filed ? \Carbon\Carbon::parse($app->date_filed)->format('F j, Y') : '' }}</td>
                            <td>
                                @if($app->inclusive_date_start && $app->inclusive_date_end)
                                    {{ \Carbon\Carbon::parse($app->inclusive_date_start)->format('F j, Y') }} - {{ \Carbon\Carbon::parse($app->inclusive_date_end)->format('F j, Y') }}
                                @elseif($app->date_incurred)
                                    {{ \Carbon\Carbon::parse($app->date_incurred)->format('F j, Y') }}
                                @endif
                            </td>
                            <td>
                                @if(!$app->is_credit_earned)
                                    {{ $app->leave_type ?? '' }}
                                @endif
                            </td>
                            <td>
                                @if(!$app->is_credit_earned && $app->leave_type === 'VL')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td>
                                @if(!$app->is_credit_earned && $app->leave_type === 'SL')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td>
                                @if(!$app->is_credit_earned && $app->leave_type === 'SPL')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td>
                                @if(!$app->is_credit_earned && $app->leave_type === 'FL')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td>
                                @if(!$app->is_credit_earned && $app->leave_type === 'SOLO PARENT')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td>
                                @if(!$app->is_credit_earned && $app->leave_type === 'OTHERS')
                                    {{ $app->working_days ?? '' }}
                                @endif
                            </td>
                            <td></td>
                            <td>{{ $app->current_vl ?? '' }}</td>
                            <td>{{ $app->current_sl ?? '' }}</td>
                            <td>
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
                                @endif
                                    <button type="button" class="delete-btn" onclick="deleteRecord({{ $app->id }}, '{{ $app->is_credit_earned ? 'credit' : 'leave' }}')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3,6 5,6 21,6"></polyline>
                                            <path d="m5,6 1,14c0,1 1,2 2,2h8c1,0 2-1 2-2l1-14"></path>
                                            <path d="m10,11 0,6"></path>
                                            <path d="m14,11 0,6"></path>
                                            <path d="M8,6V4c0-1,1-2,2-2h4c0-1,1-2,2-2v2"></path>
                                        </svg>
                                    </button>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    @endif


    <!-- Bottom: Add Leave Type and Add Earned Credits -->
    @if($employee)
        <div class="bottom-section">
            <!-- Add Leave Type -->
            <form method="POST" action="{{ route('leave.submit') }}" id="leave-form" class="leave-form">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <input type="hidden" name="edit_id" id="edit_id" value="">
                <input type="hidden" name="_method" id="form_method" value="POST">
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
            <!-- Add Earned Credits -->
            <form method="POST" action="{{ route('leave.credits') }}">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="emp-form">
                    <label>Earned Date:</label>
                    <input type="date" name="earned_date" required>
                    <button type="submit">Add Credits Earned</button>
                </div>
            </form>
        </div>
    @endif

   <!-- External Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Pass Laravel routes to JavaScript -->
    <script>
        // Make Laravel routes available to JavaScript
        window.autocompleteRoute = '{{ route("employee.autocomplete") }}';
        window.leaveUpdateRoute = '{{ route("leave.update") }}';
        window.deleteRoute = '{{ route("leave.delete") }}'; // Add this line
        window.csrfToken = '{{ csrf_token() }}'; // Add this line
    </script>
    
    <!-- Include the external JavaScript file -->
    @vite(['resources/js/leave-form.js'])

</body>
</html>