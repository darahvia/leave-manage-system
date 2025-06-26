<!DOCTYPE html>
<html>
<head>
    <title>CTO Management</title>
    @vite(['resources/css/leave.css', 'resources/js/cto-form.js'])
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .tab-nav {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 1.5rem;
        }
        .tab-link {
            padding: 0.5rem 1.5rem;
            text-decoration: none;
            color: #333;
            border-bottom: 2px solid transparent;
            transition: border 0.2s, color 0.2s;
        }
        .tab-link.active, .tab-link:hover {
            color: #007bff;
            border-bottom: 2px solid #007bff;
        }

        /* Styles for action buttons and table rows */
        .actions-column {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        .actions-column button {
            padding: 5px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .edit-btn {
            background-color: #28a745; /* Green */
            color: white;
        }
        .delete-btn {
            background-color: #dc3545; /* Red */
            color: white;
        }
        .edit-btn:hover, .delete-btn:hover {
            opacity: 0.9;
        }
        .cto-table th, .cto-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        .cto-table thead tr {
            background-color: #90EE90;
        }
        .cto-table th {
            font-weight: bold;
        }
        .cto-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
            margin-top: 1rem;
        }

        /* Row specific colors based on CTO status */
        .cto-expired {
            background-color: #ffe0e0; /* Light Red */
        }
        .cto-valid-credit {
            background-color: #e0ffe0; /* Light Green */
        }
        .cto-fully-consumed {
            background-color: #f0f0f0; /* Light Grey */
        }
        .cto-absence {
            background-color: #fffacd; /* Lemon Chiffon (light yellow) */
        }
        .balance-positive {
            color: #28a745;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="page-wrapper"> 
        <div class="tab-nav" style="margin-bottom: 1.5rem;">
            <a href="{{ route('leave.index') }}{{ $employee ? '?employee_id=' . $employee->id : '' }}" class="tab-link{{ request()->routeIs('leave.index') ? ' active' : '' }}">Leave</a>
            <a href="{{ route('cto.index') }}{{ $employee ? '?employee_id=' . $employee->id : '' }}" class="tab-link{{ request()->routeIs('cto.index') ? ' active' : '' }}">CTO</a>
        </div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="error">{{ session('error') }}</div>
        @endif

        <div class="header-wrapper">
            <div class="header-container">
                <img src="/images/deped-logo.png" alt="DepEd Logo" class="header-logo">
                <div class="header-text">
                    <div class="header-title">
                        <span class="dep">Dep</span><span class="ed">Ed</span> Cadiz City
                    </div>
                    <div class="header-subtitle">CTO Management System</div>
                </div>
                <img src="/images/deped-cadiz-logo.png" alt="Division Logo" class="header-logo">
            </div>

            <div class="search-bar-section">
                <form method="POST" action="{{ route('employee.find') }}" class="search-form" autocomplete="off">
                    @csrf
                    <input type="hidden" name="redirect_to" value="cto">
                    <div class="search-box">
                        <button type="submit" class="search-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </button>
                        <input type="text" name="name" id="employee-search" autocomplete="off" required placeholder="Find Employee...">
                        <div id="suggestions"></div>
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
                            <div style="height: 1rem;"></div>

                            <button type="submit">Add Employee</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

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
                    </tr>
                    <tr>
                        <td class="label">GIVEN NAME</td>
                        <td class="value">{{ strtoupper($employee->given_name) }}</td>
                        <td class="label">DESIGNATION</td>
                        <td class="value">{{ strtoupper($employee->designation) }}</td>
                        <td class="label">CTO BALANCE</td>
                        {{-- Display employee's current eligible CTO balance for new deductions --}}
                        <td class="value balance-positive">{{ number_format($ctoService->getEligibleCtoBalance($employee), 1) }}</td>
                    </tr>
                    <tr>
                        <td class="label">MIDDLE NAME</td>
                        <td class="value">{{ strtoupper($employee->middle_name) }}</td>
                        <td class="label">ORIGINAL APPOINTMENT</td>
                        <td class="value">{{ $employee->original_appointment ?? '' }}</td>
                        <td class="label"></td>
                        <td class="value"></td>
                    </tr>
                </table>
            </div>

            <div class="bottom-section">
                <div class="form-section">
                    <h4>Add CTO Activity (Credits Earned)</h4>
                    <form method="POST" action="{{ route('cto.store-activity') }}" id="activity-form">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                        <input type="hidden" name="cto_id" id="activity_cto_id" value=""> {{-- For editing --}}
                        <input type="hidden" name="_method" id="activity_form_method" value="POST"> {{-- For PUT on update --}}
                        
                        <div class="emp-form">
                            <label>Special Order:</label>
                            <input type="text" name="special_order" id="special_order" required>
                            
                            <label>Activity:</label>
                            <input type="text" name="activity" id="activity" required>
                            
                            <label>Credits Earned:</label>
                            <input type="number" name="credits_earned" id="credits_earned" step="0.01" required>
                            
                            <label>
                                <input type="checkbox" name="is_single_day_activity" id="single-day-activity"> Single Day Activity
                            </label>
                            
                            <label>Date of Activity (Start):</label>
                            <input type="date" name="date_of_activity_start" id="date_of_activity_start" required>
                            
                            <label id="end-date-label">Date of Activity (End):</label>
                            <input type="date" name="date_of_activity_end" id="activity-end-date">
                            
                            <button type="submit" id="submit-activity-btn">Add CTO Activity</button>
                            <button type="button" id="cancel-activity-edit-btn" onclick="cancelCtoActivityEdit()" style="display: none; margin-left: 10px; background-color: #6c757d;">Cancel</button>
                        </div>
                    </form>
                </div>

                <div class="form-section">
                    <h4>Add CTO Usage (Credits Deducted)</h4>
                    <form method="POST" action="{{ route('cto.store-usage') }}" id="usage-form">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                        <input type="hidden" name="cto_id" id="usage_cto_id" value=""> {{-- For editing --}}
                        <input type="hidden" name="_method" id="usage_form_method" value="POST"> {{-- For PUT on update --}}

                        <div class="emp-form">
                            <label>
                                <input type="checkbox" name="is_single_day_absence" id="single-day-absence"> Single Day Absence
                            </label>
                            
                            <label>Date of Absence (Start):</label>
                            <input type="date" name="date_of_absence_start" id="date_of_absence_start" required>
                            
                            <label id="absence-end-date-label">Date of Absence (End):</label>
                            <input type="date" name="date_of_absence_end" id="absence-end-date">
                            
                            <button type="submit" id="submit-usage-btn">Add CTO Usage</button>
                            <button type="button" id="cancel-usage-edit-btn" onclick="cancelCtoUsageEdit()" style="display: none; margin-left: 10px; background-color: #6c757d;">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div style="text-align: center; margin: 2rem 0;">
                <h3 style="margin: 0; padding: 0.5rem; background-color: #90EE90; border: 2px solid #000; font-weight: bold;">COMPENSATORY TIME-OFF (CTO)</h3>
            </div>
            
            <table class="cto-table">
                <thead>
                    <tr>
                        <th>SPECIAL ORDER</th>
                        <th>DATE OF ACTIVITY</th>
                        <th>ACTIVITY</th>
                        <th>EARNED</th>
                        <th>DATE OF ABSENCES</th>
                        <th>NO. OF DAYS</th>
                        <th>BALANCE</th>
                        <th>ACTIONS</th> {{-- New Actions Column --}}
                    </tr>
                </thead>
                <tbody>
                    @if($employee->ctoApplications && $employee->ctoApplications->count())
                        @foreach($employee->ctoApplications->sortBy('effective_date') as $cto)
                            @php
                                $rowClass = '';
                                if ($cto->is_activity) {
                                    if ($cto->isExpired()) { 
                                        $rowClass = 'cto-expired'; // Red
                                    } elseif ($cto->remaining_credits > 0) {
                                        $rowClass = 'cto-valid-credit'; // Green
                                    } else {
                                        $rowClass = 'cto-fully-consumed'; // Grey
                                    }
                                } else {
                                    $rowClass = 'cto-absence'; // Orange/Yellow
                                }
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td data-label="SPECIAL ORDER">
                                    {{ $cto->is_activity ? $cto->special_order : '' }}
                                </td>
                                <td data-label="DATE OF ACTIVITY">
                                    {{ $cto->is_activity ? $cto->formatted_activity_date : '' }}
                                </td>
                                <td data-label="ACTIVITY">
                                    {{ $cto->is_activity ? $cto->activity : '' }}
                                </td>
                                <td data-label="EARNED">
                                    {{ $cto->is_activity ? number_format($cto->credits_earned, 2) : '' }}
                                </td>
                                <td data-label="DATE OF ABSENCES">
                                    {{ !$cto->is_activity ? $cto->formatted_absence_date : '' }}
                                </td>
                                <td data-label="NO. OF DAYS">
                                    {{ !$cto->is_activity ? number_format($cto->no_of_days, 2) : '' }}
                                </td>
                                <td data-label="BALANCE" style="font-weight: bold;">
                                    {{ number_format($cto->balance, 1) }} 
                                </td>
                                <td data-label="ACTIONS" class="actions-column">
                                    {{-- Edit Button --}}
                                    <button type="button" class="edit-btn" onclick="editCtoRecord(
                                        {{ $cto->id }}, 
                                        {{ $cto->is_activity ? 'true' : 'false' }},
                                        '{{ $cto->special_order ?? '' }}',
                                        '{{ $cto->activity ?? '' }}',
                                        '{{ $cto->credits_earned ?? '' }}',
                                        '{{ $cto->date_of_activity_start ? $cto->date_of_activity_start->format('Y-m-d') : '' }}',
                                        '{{ $cto->date_of_activity_end ? $cto->date_of_activity_end->format('Y-m-d') : '' }}',
                                        '{{ $cto->no_of_days ?? '' }}',
                                        '{{ $cto->date_of_absence_start ? $cto->date_of_absence_start->format('Y-m-d') : '' }}',
                                        '{{ $cto->date_of_absence_end ? $cto->date_of_absence_end->format('Y-m-d') : '' }}'
                                    )">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M12 12l7-7 3 3-7 7-3 0 0-3z"></path>
                                        </svg>
                                    </button>

                                    {{-- Delete Button --}}
                                    <button type="button" class="delete-btn" onclick="deleteCtoRecord({{ $cto->id }})">
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
                    @else
                        <tr>
                            <td colspan="8" style="text-align: center; color: #6c757d;">No CTO records found</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @endif
    </div> 
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() { 
            // Pass Laravel routes to JavaScript
            window.autocompleteRoute = '{{ route("employee.autocomplete") }}';
            window.ctoEditRoute = '{{ route("cto.edit", ":id") }}';
            window.ctoUpdateRoute = '{{ route("cto.update", ":id") }}';
            window.ctoDeleteRoute = '{{ route("cto.destroy", ":id") }}';
            window.ctoCalculateDaysRoute = '{{ route("cto.calculate-days") }}'; // Added for working days calc
            window.csrfToken = '{{ csrf_token() }}';

            // Modal functionality (Add Employee) - these elements always exist
            document.getElementById('showAddEmpModal').addEventListener('click', function() {
                document.getElementById('addEmpModal').style.display = 'flex';
            });

            document.getElementById('closeAddEmpModal').addEventListener('click', function() {
                document.getElementById('addEmpModal').style.display = 'none';
            });

            // Employee search autocomplete - these elements always exist
            let debounceTimer;
            $('#employee-search').on('input', function() {
                clearTimeout(debounceTimer);
                const query = $(this).val();
                
                if (query.length < 2) {
                    $('#suggestions').empty().hide();
                    return;
                }
                
                debounceTimer = setTimeout(() => {
                    $.ajax({
                        url: window.autocompleteRoute,
                        method: 'GET',
                        data: { query: query },
                        success: function(data) {
                            $('#suggestions').empty();
                            
                            if (data.length > 0) {
                                data.forEach(function(item) {
                                    $('#suggestions').append(
                                        `<div class="suggestion-item" data-label="${item.label}">${item.label}</div>`
                                    );
                                });
                                $('#suggestions').show();
                            } else {
                                $('#suggestions').hide();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Autocomplete AJAX error:", status, error, xhr.responseText);
                            $('#suggestions').empty().hide();
                        }
                    });
                }, 300);
            });

            // Handle suggestion clicks - element always exists
            $(document).on('click', '.suggestion-item', function() {
                const label = $(this).data('label');
                $('#employee-search').val(label);
                $('#suggestions').empty().hide();
            });

            // Hide suggestions when clicking outside - element always exists
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.search-box').length) {
                    $('#suggestions').empty().hide();
                }
            });

            // --- CTO Specific JavaScript conditional on employee existence ---
            @if($employee)
                // Activity form elements
                const singleDayActivityCheckbox = document.getElementById('single-day-activity');
                const activityEndDateField = document.getElementById('activity-end-date');
                const activityEndDateLabel = document.getElementById('end-date-label');
                const dateOfActivityStartField = document.getElementById('date_of_activity_start');
                const specialOrderField = document.getElementById('special_order');
                const activityField = document.getElementById('activity');
                const creditsEarnedField = document.getElementById('credits_earned');
                const submitActivityBtn = document.getElementById('submit-activity-btn');
                const cancelActivityEditBtn = document.getElementById('cancel-activity-edit-btn');
                const activityCtoIdField = document.getElementById('activity_cto_id');
                const activityFormMethodField = document.getElementById('activity_form_method');
                const activityForm = $('#activity-form');


                // Usage form elements
                const singleDayAbsenceCheckbox = document.getElementById('single-day-absence');
                const absenceEndDateField = document.getElementById('absence-end-date');
                const absenceEndDateLabel = document.getElementById('absence-end-date-label');
                const dateOfAbsenceStartField = document.getElementById('date_of_absence_start');
                const submitUsageBtn = document.getElementById('submit-usage-btn');
                const cancelUsageEditBtn = document.getElementById('cancel-usage-edit-btn');
                const usageCtoIdField = document.getElementById('usage_cto_id');
                const usageFormMethodField = document.getElementById('usage_form_method');
                const usageForm = $('#usage-form');


                // Initial state for single day checkboxes (ensure end date fields are hidden/shown correctly on load if values are pre-filled)
                if (singleDayActivityCheckbox && activityEndDateField && activityEndDateLabel) {
                    if (singleDayActivityCheckbox.checked) {
                        activityEndDateField.style.display = 'none';
                        activityEndDateField.removeAttribute('required');
                        activityEndDateLabel.style.display = 'none';
                    } else {
                        activityEndDateField.style.display = 'block';
                        activityEndDateField.setAttribute('required', 'required');
                        activityEndDateLabel.style.display = 'block';
                    }
                    singleDayActivityCheckbox.addEventListener('change', function() {
                        if (this.checked) {
                            activityEndDateField.style.display = 'none';
                            activityEndDateField.value = ''; // Clear value when hidden
                            activityEndDateField.removeAttribute('required');
                            activityEndDateLabel.style.display = 'none';
                        } else {
                            activityEndDateField.style.display = 'block';
                            activityEndDateField.setAttribute('required', 'required');
                            activityEndDateLabel.style.display = 'block';
                        }
                    });
                }

                if (singleDayAbsenceCheckbox && absenceEndDateField && absenceEndDateLabel) {
                    if (singleDayAbsenceCheckbox.checked) {
                        absenceEndDateField.style.display = 'none';
                        absenceEndDateField.removeAttribute('required');
                        absenceEndDateLabel.style.display = 'none';
                    } else {
                        absenceEndDateField.style.display = 'block';
                        absenceEndDateField.setAttribute('required', 'required');
                        absenceEndDateLabel.style.display = 'block';
                    }
                    singleDayAbsenceCheckbox.addEventListener('change', function() {
                        if (this.checked) {
                            absenceEndDateField.style.display = 'none';
                            absenceEndDateField.value = ''; // Clear value when hidden
                            absenceEndDateField.removeAttribute('required');
                            absenceEndDateLabel.style.display = 'none';
                        } else {
                            absenceEndDateField.style.display = 'block';
                            absenceEndDateField.setAttribute('required', 'required');
                            absenceEndDateLabel.style.display = 'block';
                        }
                    });
                }

                // Function to calculate working days (for CTO usage)
                async function calculateWorkingDaysForUsage() {
                    const startDate = dateOfAbsenceStartField.value;
                    const endDate = singleDayAbsenceCheckbox.checked ? startDate : absenceEndDateField.value;

                    if (startDate) {
                        try {
                            const response = await fetch(window.ctoCalculateDaysRoute, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': window.csrfToken
                                },
                                body: JSON.stringify({ start_date: startDate, end_date: endDate })
                            });
                            const data = await response.json();
                            if (response.ok) {
                                // You might want to display this somewhere, or just use it for validation
                                // console.log('Calculated working days:', data.days);
                            } else {
                                console.error('Error calculating days:', data.message);
                                alert('Error calculating days: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Fetch error calculating days:', error);
                            alert('An error occurred while calculating days.');
                        }
                    }
                }

                // Attach event listeners for date changes in usage form to recalculate days
                if (dateOfAbsenceStartField) {
                    dateOfAbsenceStartField.addEventListener('change', calculateWorkingDaysForUsage);
                }
                if (absenceEndDateField) {
                    absenceEndDateField.addEventListener('change', calculateWorkingDaysForUsage);
                }
                if (singleDayAbsenceCheckbox) {
                    singleDayAbsenceCheckbox.addEventListener('change', calculateWorkingDaysForUsage);
                }


                // Edit function for CTO records
                window.editCtoRecord = function(id, is_activity, special_order, activity, credits_earned, date_of_activity_start, date_of_activity_end, no_of_days, date_of_absence_start, date_of_absence_end) {
                    if (is_activity) {
                        // Populate Activity Form
                        activityCtoIdField.value = id;
                        activityForm.attr('action', window.ctoUpdateRoute.replace(':id', id));
                        activityFormMethodField.value = 'PUT'; // Set to PUT for update
                        submitActivityBtn.textContent = 'Update CTO Activity';
                        cancelActivityEditBtn.style.display = 'inline-block';

                        specialOrderField.value = special_order;
                        activityField.value = activity;
                        creditsEarnedField.value = credits_earned;
                        dateOfActivityStartField.value = date_of_activity_start;

                        if (date_of_activity_start === date_of_activity_end) {
                            singleDayActivityCheckbox.checked = true;
                            activityEndDateField.style.display = 'none';
                            activityEndDateField.removeAttribute('required');
                            activityEndDateLabel.style.display = 'none';
                        } else {
                            singleDayActivityCheckbox.checked = false;
                            activityEndDateField.style.display = 'block';
                            activityEndDateField.setAttribute('required', 'required');
                            activityEndDateField.value = date_of_activity_end;
                            activityEndDateLabel.style.display = 'block';
                        }
                        
                        // Scroll to the activity form
                        activityForm[0].scrollIntoView({ behavior: 'smooth', block: 'center' });

                    } else {
                        // Populate Usage Form
                        usageCtoIdField.value = id;
                        usageForm.attr('action', window.ctoUpdateRoute.replace(':id', id));
                        usageFormMethodField.value = 'PUT'; // Set to PUT for update
                        submitUsageBtn.textContent = 'Update CTO Usage';
                        cancelUsageEditBtn.style.display = 'inline-block';

                        dateOfAbsenceStartField.value = date_of_absence_start;

                        if (date_of_absence_start === date_of_absence_end) {
                            singleDayAbsenceCheckbox.checked = true;
                            absenceEndDateField.style.display = 'none';
                            absenceEndDateField.removeAttribute('required');
                            absenceEndDateLabel.style.display = 'none';
                        } else {
                            singleDayAbsenceCheckbox.checked = false;
                            absenceEndDateField.style.display = 'block';
                            absenceEndDateField.setAttribute('required', 'required');
                            absenceEndDateField.value = date_of_absence_end;
                            absenceEndDateLabel.style.display = 'block';
                        }
                        
                        // Scroll to the usage form
                        usageForm[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                };

                // Cancel Edit functions
                window.cancelCtoActivityEdit = function() {
                    activityCtoIdField.value = '';
                    activityForm.attr('action', '{{ route('cto.store-activity') }}');
                    activityFormMethodField.value = 'POST';
                    submitActivityBtn.textContent = 'Add CTO Activity';
                    cancelActivityEditBtn.style.display = 'none';
                    activityForm[0].reset(); // Reset form fields
                    // Ensure end date visibility is reset
                    singleDayActivityCheckbox.checked = false;
                    activityEndDateField.style.display = 'block';
                    activityEndDateField.setAttribute('required', 'required');
                    activityEndDateLabel.style.display = 'block';
                };

                window.cancelCtoUsageEdit = function() {
                    usageCtoIdField.value = '';
                    usageForm.attr('action', '{{ route('cto.store-usage') }}');
                    usageFormMethodField.value = 'POST';
                    submitUsageBtn.textContent = 'Add CTO Usage';
                    cancelUsageEditBtn.style.display = 'none';
                    usageForm[0].reset(); // Reset form fields
                    // Ensure end date visibility is reset
                    singleDayAbsenceCheckbox.checked = false;
                    absenceEndDateField.style.display = 'block';
                    absenceEndDateField.setAttribute('required', 'required');
                    absenceEndDateLabel.style.display = 'block';
                };


                // Delete function for CTO records
                window.deleteCtoRecord = function(id) {
                    if (confirm('Are you sure you want to delete this CTO record? This action cannot be undone and will recalculate balances.')) {
                        const deleteUrl = window.ctoDeleteRoute.replace(':id', id);
                        $.ajax({
                            url: deleteUrl,
                            type: 'POST', // Laravel uses POST for DELETE via _method spoofing
                            data: {
                                _method: 'DELETE',
                                _token: window.csrfToken
                            },
                            success: function(response) {
                                alert(response.success || 'CTO record deleted successfully.');
                                location.reload(); // Reload the page to reflect changes
                            },
                            error: function(xhr, status, error) {
                                console.error("Delete CTO record error:", status, error, xhr.responseText);
                                alert(xhr.responseJSON.error || 'Failed to delete CTO record.');
                            }
                        });
                    }
                };
            @endif {{-- End of @if($employee) conditional for JS --}}

        }); {{-- End of $(document).ready() --}}
    </script>
</body>
</html>
