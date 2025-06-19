{{-- filepath: resources/views/leave/index.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Application for Leave</title>
    @vite(['resources/css/leave.css'])
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
        <form method="POST" action="{{ route('employee.find') }}" class="search-form">
            @csrf
            <div class="emp-form">
                <label>Find Employee:</label>
                <input type="text" name="name" required>
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
        </div>
    </div>

    <!-- Employee Details -->
    @if($employee)
        <div class="emp-details">
            <b>Name:</b> {{ $employee->name }}<br>
            <b>Division:</b> {{ $employee->division }}<br>
            <b>Designation:</b> {{ $employee->designation }}<br>
            <b>Salary:</b> {{ $employee->salary }}<br>
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
                            <td>{{ $app->date_incurred ? \Carbon\Carbon::parse($app->date_incurred)->format('F j, Y') : '' }}</td>
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
    @endif

    <!-- Bottom: Add Leave Type and Add Earned Credits -->
    @if($employee)
        <div class="bottom-section">
            <!-- Add Leave Type -->
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
                    <button type="submit">Add Leave</button>
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

    <script>
        // Modal logic for Add Employee
        document.getElementById('showAddEmpModal').onclick = function() {
            document.getElementById('addEmpModal').classList.add('active');
        };
        document.getElementById('closeAddEmpModal').onclick = function() {
            document.getElementById('addEmpModal').classList.remove('active');
        };
        // Optional: close modal when clicking outside content
        document.getElementById('addEmpModal').onclick = function(e) {
            if (e.target === this) this.classList.remove('active');
        };
    </script>
</body>
</html>