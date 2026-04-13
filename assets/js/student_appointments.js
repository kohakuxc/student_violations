document.addEventListener('DOMContentLoaded', function() 
{
    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');
    const dateInput = document.getElementById('date');
    const timeSelect = document.getElementById('time');

    // Add this to the DOMContentLoaded section
const appointmentForm = document.getElementById('appointmentForm');
if (appointmentForm) {
    appointmentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('api/create_appointment.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('Appointment created successfully!');
                // Reload the page to show the new appointment
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('An error occurred while creating the appointment');
        });
    });
}

    // Handle category change
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            const categoryId = this.value;
            
            subcategorySelect.innerHTML = '<option value="">-- Select Type --</option>';
            subcategorySelect.disabled = true;
            timeSelect.innerHTML = '<option value="">-- Select Time --</option>';
            timeSelect.disabled = true;

            if (categoryId) {
                fetch('api/appointments.php?action=getSubcategories&category_id=' + categoryId)
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('HTTP error, status = ' + response.status);
                        }
                        return response.json();
                    })
                    .then(function(data) {
                        if (data.success && data.data && data.data.length > 0) {
                            data.data.forEach(function(subcat) {
                                const option = document.createElement('option');
                                option.value = subcat.subcategory_id;
                                option.textContent = subcat.subcategory_name;
                                subcategorySelect.appendChild(option);
                            });
                            subcategorySelect.disabled = false;
                        } else {
                            subcategorySelect.innerHTML = '<option value="">No types available</option>';
                        }
                    })
                    .catch(function(error) {
                        console.error('Error:', error);
                        subcategorySelect.innerHTML = '<option value="">Error loading types</option>';
                    });
            }
        });
    }

    // Handle date change
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            
            timeSelect.innerHTML = '<option value="">-- Select Time --</option>';
            timeSelect.disabled = true;

            if (selectedDate) {
                const date = new Date(selectedDate);
                const dayOfWeek = date.getDay();

                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    alert('Appointments can only be scheduled on weekdays (Monday-Friday)');
                    this.value = '';
                    return;
                }

                fetch('api/appointments.php?action=getAvailableSlots&date=' + selectedDate)
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('HTTP error, status = ' + response.status);
                        }
                        return response.json();
                    })
                    .then(function(data) {
                        if (data.success && data.data && data.data.length > 0) {
                            data.data.forEach(function(slot) {
                                const option = document.createElement('option');
                                option.value = slot.time;
                                option.textContent = slot.time;
                                timeSelect.appendChild(option);
                            });
                            timeSelect.disabled = false;
                        } else {
                            timeSelect.innerHTML = '<option value="">No slots available</option>';
                        }
                    })
                    .catch(function(error) {
                        console.error('Error:', error);
                        timeSelect.innerHTML = '<option value="">Error loading slots</option>';
                    });
            }
        });
    }

    // File preview
    const fileInput = document.getElementById('evidence_image');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const filePreview = document.getElementById('file-preview');
            filePreview.innerHTML = '';

            if (this.files.length > 0) {
                const file = this.files[0];
                const maxSize = 3 * 1024 * 1024;
                
                if (file.size > maxSize) {
                    filePreview.innerHTML = '<div class="alert alert-danger">File size exceeds 3MB limit</div>';
                    this.value = '';
                    return;
                }

                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg'];
                if (!allowedTypes.includes(file.type)) {
                    filePreview.innerHTML = '<div class="alert alert-danger">Only PDF and JPG files are allowed</div>';
                    this.value = '';
                    return;
                }

                filePreview.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + file.name + '</div>';
            }
        });
    }
});