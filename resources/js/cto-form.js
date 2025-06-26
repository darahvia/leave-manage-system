// resources/js/cto-form.js

// This script contains the CTO-specific JavaScript logic,
// including form handling, date calculations, and action button functionality.

// Ensure the DOM is fully loaded before executing scripts
$(document).ready(function() {

    // These global variables are expected to be set in the blade file
    // before this script is loaded.
    // window.autocompleteRoute
    // window.ctoEditRoute
    // window.ctoUpdateRoute
    // window.ctoDeleteRoute
    // window.ctoCalculateDaysRoute
    // window.csrfToken
    // window.ctoStoreActivityRoute (NEWLY ADDED IN BLADE FOR EXTERNAL JS)
    // window.ctoStoreUsageRoute (NEWLY ADDED IN BLADE FOR EXTERNAL JS)

    // --- Employee Search (Common for both Leave and CTO pages) ---
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

    // Handle suggestion clicks
    $(document).on('click', '.suggestion-item', function() {
        const label = $(this).data('label');
        $('#employee-search').val(label);
        $('#suggestions').empty().hide();
    });

    // Hide suggestions when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-box').length) {
            $('#suggestions').empty().hide();
        }
    });

    // --- Modal functionality (Add Employee) ---
    // These elements always exist regardless of whether an employee is loaded.
    const showAddEmpModalBtn = document.getElementById('showAddEmpModal');
    const closeAddEmpModalBtn = document.getElementById('closeAddEmpModal');
    const addEmpModal = document.getElementById('addEmpModal');

    if (showAddEmpModalBtn && addEmpModal) {
        showAddEmpModalBtn.addEventListener('click', function() {
            addEmpModal.style.display = 'flex';
        });
    }

    if (closeAddEmpModalBtn && addEmpModal) {
        closeAddEmpModalBtn.addEventListener('click', function() {
            addEmpModal.style.display = 'none';
        });
    }

    // --- CTO Specific JavaScript (conditional on employee existence) ---
    // These elements and functions only apply if an employee is loaded,
    // meaning the forms are actually present in the HTML.
    // The presence check is done implicitly by trying to get the element.
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
    const activityForm = $('#activity-form'); // Using jQuery for form submission handling

    const singleDayAbsenceCheckbox = document.getElementById('single-day-absence');
    const absenceEndDateField = document.getElementById('absence-end-date');
    const absenceEndDateLabel = document.getElementById('absence-end-date-label');
    const dateOfAbsenceStartField = document.getElementById('date_of_absence_start');
    const submitUsageBtn = document.getElementById('submit-usage-btn');
    const cancelUsageEditBtn = document.getElementById('cancel-usage-edit-btn');
    const usageCtoIdField = document.getElementById('usage_cto_id');
    const usageFormMethodField = document.getElementById('usage_form_method');
    const usageForm = $('#usage-form'); // Using jQuery for form submission handling


    // Initial state for single day checkboxes (ensure end date fields are hidden/shown correctly on load)
    if (singleDayActivityCheckbox && activityEndDateField && activityEndDateLabel) {
        // Set initial state based on checkbox
        if (singleDayActivityCheckbox.checked) {
            activityEndDateField.style.display = 'none';
            activityEndDateField.removeAttribute('required');
            activityEndDateLabel.style.display = 'none';
        } else {
            activityEndDateField.style.display = 'block';
            activityEndDateField.setAttribute('required', 'required');
            activityEndDateLabel.style.display = 'block';
        }
        // Add event listener
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
        // Set initial state based on checkbox
        if (singleDayAbsenceCheckbox.checked) {
            absenceEndDateField.style.display = 'none';
            absenceEndDateField.removeAttribute('required');
            absenceEndDateLabel.style.display = 'none';
        } else {
            absenceEndDateField.style.display = 'block';
            absenceEndDateField.setAttribute('required', 'required');
            absenceEndDateLabel.style.display = 'block';
        }
        // Add event listener
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
        // Only run if the elements exist (i.e., an employee is loaded)
        if (!dateOfAbsenceStartField || !absenceEndDateField || !singleDayAbsenceCheckbox) {
            return;
        }

        const startDate = dateOfAbsenceStartField.value;
        // If single day, end date is same as start date for calculation
        const endDate = singleDayAbsenceCheckbox.checked ? startDate : absenceEndDateField.value;

        if (startDate && endDate) { // Ensure both dates are selected/available
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
                    // console.log('Calculated working days:', data.days);
                    // You might want to update a hidden field or display this to the user
                    // For now, it's just logged or used internally by the server.
                } else {
                    console.error('Error calculating days:', data.message);
                    // Using a custom modal/div for error messages instead of alert()
                    displayMessage('Error calculating days: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Fetch error calculating days:', error);
                displayMessage('An error occurred while calculating days.', 'error');
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

    // Edit function for CTO records (exposed globally)
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

            if (date_of_activity_start && date_of_activity_end && date_of_activity_start === date_of_activity_end) {
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

            if (date_of_absence_start && date_of_absence_end && date_of_absence_start === date_of_absence_end) {
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

    // Cancel Edit functions (exposed globally)
    window.cancelCtoActivityEdit = function() {
        activityCtoIdField.value = '';
        // CORRECTED: Use the window variable for the route
        activityForm.attr('action', window.ctoStoreActivityRoute); 
        activityFormMethodField.value = 'POST';
        submitActivityBtn.textContent = 'Add CTO Activity';
        cancelActivityEditBtn.style.display = 'none';
        activityForm[0].reset(); // Reset form fields
        // Ensure end date visibility is reset
        singleDayActivityCheckbox.checked = false; // Reset checkbox state
        activityEndDateField.style.display = 'block';
        activityEndDateField.setAttribute('required', 'required');
        activityEndDateField.value = ''; // Clear date value
        activityEndDateLabel.style.display = 'block';
    };

    window.cancelCtoUsageEdit = function() {
        usageCtoIdField.value = '';
        // CORRECTED: Use the window variable for the route
        usageForm.attr('action', window.ctoStoreUsageRoute); 
        usageFormMethodField.value = 'POST';
        submitUsageBtn.textContent = 'Add CTO Usage';
        cancelUsageEditBtn.style.display = 'none';
        usageForm[0].reset(); // Reset form fields
        // Ensure end date visibility is reset
        singleDayAbsenceCheckbox.checked = false; // Reset checkbox state
        absenceEndDateField.style.display = 'block';
        absenceEndDateField.setAttribute('required', 'required');
        absenceEndDateField.value = ''; // Clear date value
        absenceEndDateLabel.style.display = 'block';
    };


    // Delete function for CTO records (exposed globally)
    window.deleteCtoRecord = function(id) {
        // Using a custom modal/div for confirmation instead of alert()
        showConfirmationModal('Are you sure you want to delete this CTO record? This action cannot be undone and will recalculate balances.', function() {
            const deleteUrl = window.ctoDeleteRoute.replace(':id', id);
            $.ajax({
                url: deleteUrl,
                type: 'POST', // Laravel uses POST for DELETE via _method spoofing
                data: {
                    _method: 'DELETE',
                    _token: window.csrfToken
                },
                success: function(response) {
                    displayMessage(response.success || 'CTO record deleted successfully.', 'success');
                    location.reload(); // Reload the page to reflect changes
                },
                error: function(xhr, status, error) {
                    console.error("Delete CTO record error:", status, error, xhr.responseText);
                    displayMessage(xhr.responseJSON.error || 'Failed to delete CTO record.', 'error');
                }
            });
        });
    };

    // --- Custom Modal/Message Box Functions (replacing alert/confirm) ---

    // Function to display messages (success/error)
    function displayMessage(message, type) {
        let messageBox = document.getElementById('custom-message-box');
        if (!messageBox) {
            messageBox = document.createElement('div');
            messageBox.id = 'custom-message-box';
            messageBox.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                padding: 15px 25px;
                border-radius: 8px;
                font-weight: bold;
                color: white;
                z-index: 1000;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                display: none; /* Hidden by default */
                opacity: 0;
                transition: opacity 0.3s ease-in-out;
            `;
            document.body.appendChild(messageBox);
        }

        messageBox.textContent = message;
        messageBox.style.backgroundColor = type === 'success' ? '#28a745' : '#dc3545';
        messageBox.style.display = 'block';
        setTimeout(() => { messageBox.style.opacity = '1'; }, 10); // Fade in

        setTimeout(() => {
            messageBox.style.opacity = '0';
            setTimeout(() => { messageBox.style.display = 'none'; }, 300); // Fade out and hide
        }, 3000); // Display for 3 seconds
    }

    // Function to show a custom confirmation modal
    function showConfirmationModal(message, onConfirmCallback) {
        let modalOverlay = document.getElementById('custom-confirm-overlay');
        if (!modalOverlay) {
            modalOverlay = document.createElement('div');
            modalOverlay.id = 'custom-confirm-overlay';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            `;
            document.body.appendChild(modalOverlay);

            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
                text-align: center;
                max-width: 400px;
                width: 90%;
            `;
            modalOverlay.appendChild(modalContent);

            const messagePara = document.createElement('p');
            messagePara.id = 'confirm-message-text';
            messagePara.style.marginBottom = '20px';
            messagePara.style.fontSize = '1.1em';
            modalContent.appendChild(messagePara);

            const buttonContainer = document.createElement('div');
            const confirmButton = document.createElement('button');
            confirmButton.textContent = 'Confirm';
            confirmButton.style.cssText = `
                background-color: #dc3545; /* Red */
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                margin-right: 10px;
                font-size: 1em;
            `;
            confirmButton.onclick = function() {
                modalOverlay.style.display = 'none';
                if (onConfirmCallback) {
                    onConfirmCallback();
                }
            };
            buttonContainer.appendChild(confirmButton);

            const cancelButton = document.createElement('button');
            cancelButton.textContent = 'Cancel';
            cancelButton.style.cssText = `
                background-color: #6c757d; /* Grey */
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 1em;
            `;
            cancelButton.onclick = function() {
                modalOverlay.style.display = 'none';
            };
            buttonContainer.appendChild(cancelButton);
            modalContent.appendChild(buttonContainer);
        }

        document.getElementById('confirm-message-text').textContent = message;
        modalOverlay.style.display = 'flex';
    }

}); // End of document.ready
