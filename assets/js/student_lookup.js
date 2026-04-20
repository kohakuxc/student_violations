document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('student_lookup');
    var hiddenId = document.getElementById('student_id');
    var results = document.getElementById('student_lookup_results');
    var selectedCard = document.getElementById('selected_student_card');
    var selectedName = document.getElementById('selected_student_name');
    var selectedMeta = document.getElementById('selected_student_meta');

    if (!input || !results) {
        return;
    }

    var debounceTimer = null;
    var requestToken = 0;

    function hideResults() {
        results.classList.add('d-none');
        results.innerHTML = '';
    }

    function setSelectedStudent(student) {
        if (hiddenId) {
            hiddenId.value = student.student_id || '';
        }
        if (selectedCard && selectedName && selectedMeta) {
            selectedName.textContent = student.name || '';
            selectedMeta.textContent = [student.student_number || '', student.email || '']
                .filter(function (value) { return value; })
                .join(' · ');
            selectedCard.classList.remove('d-none');
        }
    }

    function clearSelectedStudent() {
        if (hiddenId) {
            hiddenId.value = '';
        }
        if (selectedCard && selectedName && selectedMeta) {
            selectedName.textContent = '';
            selectedMeta.textContent = '';
            selectedCard.classList.add('d-none');
        }
    }

    function renderResults(students) {
        results.innerHTML = '';

        if (!students.length) {
            var emptyItem = document.createElement('div');
            emptyItem.className = 'student-lookup-empty';
            emptyItem.textContent = 'No matching students found';
            results.appendChild(emptyItem);
            results.classList.remove('d-none');
            return;
        }

        students.forEach(function (student) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'student-lookup-item';
            button.dataset.studentId = student.student_id;
            button.dataset.studentName = student.name || '';
            button.dataset.studentNumber = student.student_number || '';
            button.dataset.studentEmail = student.email || '';

            var title = document.createElement('strong');
            title.textContent = student.name || 'Unnamed student';

            var meta = document.createElement('span');
            meta.textContent = [student.student_number || '', student.email || '']
                .filter(function (value) { return value; })
                .join(' · ');

            button.appendChild(title);
            if (meta.textContent) {
                button.appendChild(meta);
            }

            button.addEventListener('click', function () {
                input.value = student.name || '';
                setSelectedStudent(student);
                hideResults();
            });

            results.appendChild(button);
        });

        results.classList.remove('d-none');
    }

    function lookupStudents() {
        var query = input.value.trim();

        if (query.length === 0) {
            clearSelectedStudent();
            hideResults();
            return;
        }

        var token = ++requestToken;

        fetch('api/student_lookup.php?q=' + encodeURIComponent(query), {
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Lookup failed');
                }
                return response.json();
            })
            .then(function (data) {
                if (token !== requestToken) {
                    return;
                }

                if (data && data.success && Array.isArray(data.students)) {
                    renderResults(data.students);
                } else {
                    hideResults();
                }
            })
            .catch(function () {
                if (token === requestToken) {
                    hideResults();
                }
            });
    }

    input.addEventListener('input', function () {
        clearSelectedStudent();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(lookupStudents, 250);
    });

    input.addEventListener('focus', function () {
        if (input.value.trim().length > 0) {
            lookupStudents();
        }
    });

    input.addEventListener('blur', function () {
        setTimeout(hideResults, 150);
    });

    document.addEventListener('click', function (event) {
        if (!results.contains(event.target) && event.target !== input) {
            hideResults();
        }
    });
});