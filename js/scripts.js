// Rowing Regatta Management System JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Handle race selection for results page
    const yearFilter = document.getElementById('yearFilter');
    if (yearFilter) {
        yearFilter.addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    // Function to handle the race participant management
    function setupRaceParticipantManager() {
        const raceSelect = document.getElementById('select_race');
        if (!raceSelect) return;
        
        const raceResultsForm = document.getElementById('race_results_form');
        const selectedRaceName = document.getElementById('selected_race_name');
        const resultsTable = document.getElementById('results_table')?.querySelector('tbody');
        
        raceSelect.addEventListener('change', function() {
            const raceId = this.value;
            
            if (!raceId) {
                if (raceResultsForm) raceResultsForm.style.display = 'none';
                return;
            }
            
            // We would fetch race participants here, but since we don't have an AJAX endpoint yet,
            // we'll just show a placeholder message
            raceResultsForm.style.display = 'block';
            selectedRaceName.textContent = `Race ID: ${raceId} (Select a race to view participants)`;
            
            // In a real implementation, you would fetch data from the server 
            // and populate the results table. For now, we'll just clear it.
            if (resultsTable) {
                resultsTable.innerHTML = '<tr><td colspan="5" class="text-center">Select a race to view and edit results</td></tr>';
            }
        });
    }
    
    // Setup race participant manager if we're on the admin page
    setupRaceParticipantManager();
    
    // Time formatting helpers
    function formatTimeInput(input) {
        // Remove non-numeric characters except colon and period
        let value = input.value.replace(/[^\d:.]/g, '');
        
        // Format as MM:SS.ms
        const parts = value.split(':');
        
        if (parts.length === 1) {
            // Only seconds provided, format as 00:SS.ms
            const secondsParts = parts[0].split('.');
            const seconds = secondsParts[0].padStart(2, '0');
            const ms = secondsParts.length > 1 ? secondsParts[1].substring(0, 3).padEnd(3, '0') : '000';
            
            value = `00:${seconds}.${ms}`;
        } else if (parts.length === 2) {
            // MM:SS format, ensure proper formatting
            const minutes = parts[0].padStart(2, '0');
            
            const secondsParts = parts[1].split('.');
            const seconds = secondsParts[0].padStart(2, '0');
            const ms = secondsParts.length > 1 ? secondsParts[1].substring(0, 3).padEnd(3, '0') : '000';
            
            value = `${minutes}:${seconds}.${ms}`;
        }
        
        input.value = value;
    }
    
    // Set up time input formatters
    document.querySelectorAll('input[name="finish_time"], input[name="time"]').forEach(input => {
        input.addEventListener('blur', function() {
            formatTimeInput(this);
        });
    });
    
    // Handle distance markers input
    const distanceMarkersInput = document.getElementById('distance_markers');
    if (distanceMarkersInput) {
        distanceMarkersInput.addEventListener('blur', function() {
            // Format as comma-separated values
            let markers = this.value.split(',')
                .map(m => m.trim())
                .filter(m => m !== '' && !isNaN(m))
                .map(m => parseInt(m, 10))
                .sort((a, b) => a - b);
            
            this.value = markers.join(',');
        });
    }
    
    // Populate distance dropdown based on race selection
    const distanceRpIdSelect = document.getElementById('distance_rp_id');
    const distancePointInput = document.getElementById('distance_point');
    
    if (distanceRpIdSelect && distancePointInput) {
        distanceRpIdSelect.addEventListener('change', async function() {
            const raceParticipantId = this.value;
            if (!raceParticipantId) return;
            
            try {
                // Fetch race details to get the distance markers
                const response = await fetch(`get_race_participant_details.php?id=${raceParticipantId}`);
                const data = await response.json();
                
                if (data.success && data.race && data.race.distance_markers) {
                    // Create a datalist for the distance input
                    let datalistId = 'distanceMarkersList';
                    let datalist = document.getElementById(datalistId);
                    
                    if (!datalist) {
                        datalist = document.createElement('datalist');
                        datalist.id = datalistId;
                        document.body.appendChild(datalist);
                        distancePointInput.setAttribute('list', datalistId);
                    }
                    
                    // Clear existing options
                    datalist.innerHTML = '';
                    
                    // Add distance markers as options
                    const markers = data.race.distance_markers.split(',');
                    markers.forEach(marker => {
                        const option = document.createElement('option');
                        option.value = marker.trim();
                        datalist.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error fetching race details:', error);
            }
        });
    }
    
    // Handle tab state persistence
    const triggerTabList = [].slice.call(document.querySelectorAll('#adminTabs button'));
    triggerTabList.forEach(function(triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function(event) {
            event.preventDefault();
            tabTrigger.show();
            // Store the currently active tab in sessionStorage
            sessionStorage.setItem('activeAdminTab', this.getAttribute('id'));
        });
    });
    
    // Restore active tab from sessionStorage
    const activeTab = sessionStorage.getItem('activeAdminTab');
    if (activeTab) {
        const tab = document.querySelector('#' + activeTab);
        if (tab) {
            const instance = bootstrap.Tab.getInstance(tab);
            if (instance) {
                instance.show();
            } else {
                // If the instance doesn't exist yet, just add the active class
                tab.classList.add('active');
                // Also show the corresponding tab content
                const contentId = tab.getAttribute('data-bs-target');
                const content = document.querySelector(contentId);
                if (content) {
                    content.classList.add('show', 'active');
                }
            }
        }
    }
    
    // Add a confirmation dialog for important actions
    const confirmForms = document.querySelectorAll('.confirm-action');
    confirmForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = this.getAttribute('data-confirm-message') || 'Are you sure you want to perform this action?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Highlight current navigation link
    const currentPage = window.location.search.split('page=')[1]?.split('&')[0] || 'home';
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && ((href.includes(`page=${currentPage}`)) || 
                   (href === 'index.php' && currentPage === 'home'))) {
            link.classList.add('active');
        }
    });
}); 