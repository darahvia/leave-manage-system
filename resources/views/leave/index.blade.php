<!DOCTYPE html>
<html>
<head>
    <title>Application for Leave</title>
    <style>
        .emp-form { margin: 15px 0; padding: 12px; background: #f9f9f9; border: 1px solid #ddd; width: 350px; }
        .emp-form label { display: block; margin-top: 8px; }
        .emp-form input, .emp-form select { width: 100%; padding: 4px 6px; margin-top: 2px; }
        .emp-details { margin: 10px 0 15px 0; padding: 10px; background: #e7f7e7; border: 1px solid #b2d8b2; width: 350px; }
        .leave-table { border-collapse: collapse; width: 100%; }
        .leave-table th, .leave-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="error">{{ session('error') }}</div>
    @endif

    <!-- Employee Management -->
    <form method="POST" action="{{ route('employee.find') }}">
        @csrf
        <div class="emp-form">
            <label>Find Employee:</label>
            <input type="text" name="name" required>
            <button type="submit">Find Employee</button>
        </div>
    </form>

    <form method="POST" action="{{ route('employee.add') }}">
        @csrf
        <div class="emp-form">
            <label>Name:</label>
            <input type="text" name="name" required>
            <label>Division:</label>
            <input type="text" name="division" required>
            <label>Designation:</label>
            <input type="text" name="designation" required>
            <label>Salary:</label>
            <input type="number" step="0.01" name="salary" required>
            <button type="submit">Add Employee</button>
        </div>
    </form>

    @if($employee)
        <div class="emp-details">
            <b>Name:</b> {{ $employee->name }}<br>
            <b>Division:</b> {{ $employee->division }}<br>
            <b>Designation:</b> {{ $employee->designation }}<br>
            <b>Salary:</b> {{ $employee->salary }}<br>
        </div>

        <!-- Leave Application Form -->
        <form method="POST" action="{{ route('leave.submit') }}">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
            <div class="emp-form">
                <label>Leave Type:</label>
                <select name="leave_type" required>
                    <option value="VL">Vacation Leave</option>
                    <option value="SL">Sick Leave</option>
                    <option value="ML">Maternity Leave</option>
                    <option value="PL">Paternity Leave</option>
                    <option value="SPL">Special Leave</option>
                    <option value="FL">Force Leave</option>
                    <option value="SOLO PARENT">Solo Parent</option>
                    <option value="OTHERS">Others</option>
                </select>
                <label>Working Days:</label>
                <input type="number" name="working_days" required>
                <label>Date Filed:</label>
                <input type="date" name="date_filed" required>
                <label>Date Incurred:</label>
                <input type="date" name="date_incurred" required>
                <button type="submit">Submit Leave</button>
            </div>
        </form>

        <!-- Leave Records Table -->
        <table class="leave-table">
            <thead>
                <tr>
                    <th>PERIOD</th>
                    <th>LEAVE CREDITS EARNED</th>
                    <th></th>
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
                </tr>
                @if($employee->leaveApplications && $employee->leaveApplications->count())
                    @foreach($employee->leaveApplications as $app)
                        <tr>
                            <td>{{ $app->earned_date ?? '' }}</td>
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
                            <td>{{ $app->date_filed ?? '' }}</td>
                            <td>{{ $app->date_incurred ?? '' }}</td>
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
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>

        <!-- Add Credits Form -->
        <form method="POST" action="{{ route('leave.credits') }}">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
            <div class="emp-form">
                <label>Earned Date:</label>
                <input type="date" name="earned_date" required>
                <button type="submit">Add Credits Earned</button>
            </div>
        </form>
    @endif
</body>
</html>