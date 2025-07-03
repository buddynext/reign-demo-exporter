jQuery(document).ready(function($) {
    'use strict';
    
    // Export steps
    const exportSteps = [
        { step: 'preparing', message: 'Preparing export...', weight: 5 },
        { step: 'scanning_content', message: 'Scanning site content...', weight: 20 },
        { step: 'analyzing_plugins', message: 'Analyzing plugins...', weight: 10 },
        { step: 'scanning_files', message: 'Scanning media files...', weight: 15 },
        { step: 'creating_manifests', message: 'Creating manifest files...', weight: 20 },
        { step: 'packaging_content', message: 'Packaging content...', weight: 25 },
        { step: 'finalizing', message: 'Finalizing export...', weight: 5 }
    ];
    
    let currentStep = 0;
    let totalProgress = 0;
    let isExporting = false;
    
    // Start Export button click
    $('#start-export').on('click', function() {
        if (isExporting) {
            return;
        }
        
        if (!confirm(reign_demo_exporter.messages.confirm_export || reign_demo_exporter_data.confirm_export || 'Are you sure you want to start the export? This may take several minutes.')) {
            return;
        }
        
        startExport();
    });
    
    // Check Requirements button click
    $('#check-requirements').on('click', function() {
        checkRequirements();
    });
    
    function startExport() {
        isExporting = true;
        currentStep = 0;
        totalProgress = 0;
        
        // Update UI
        $('#start-export').prop('disabled', true).text('Exporting...');
        $('.export-progress').slideDown();
        $('.export-success, .export-error').hide();
        
        // Start the export process
        processNextStep();
    }
    
    function processNextStep() {
        if (currentStep >= exportSteps.length) {
            completeExport();
            return;
        }
        
        const step = exportSteps[currentStep];
        updateProgress(step.message);
        
        $.ajax({
            url: reign_demo_exporter.ajax_url,
            type: 'POST',
            data: {
                action: 'reign_demo_export_step',
                step: step.step,
                nonce: reign_demo_exporter.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Calculate progress
                    totalProgress += step.weight;
                    updateProgressBar(totalProgress);
                    
                    // Move to next step
                    currentStep++;
                    setTimeout(function() {
                        processNextStep();
                    }, 500);
                } else {
                    handleError(response.data.message || 'Export failed');
                }
            },
            error: function(xhr, status, error) {
                handleError('Network error: ' + error);
            }
        });
    }
    
    function completeExport() {
        updateProgress('Retrieving export results...');
        
        // Get final export data
        $.ajax({
            url: reign_demo_exporter.ajax_url,
            type: 'POST',
            data: {
                action: 'reign_demo_get_export_results',
                nonce: reign_demo_exporter.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data);
                } else {
                    handleError(response.data.message || 'Failed to retrieve export results');
                }
            },
            error: function() {
                // Even if this fails, the export might have succeeded
                showSuccess({
                    message: 'Export completed! Check the export directory for files.',
                    files: {
                        manifest: reign_demo_exporter.export_url + 'manifest.json',
                        plugins: reign_demo_exporter.export_url + 'plugins-manifest.json',
                        files: reign_demo_exporter.export_url + 'files-manifest.json',
                        package: reign_demo_exporter.export_url + 'content-package.zip'
                    }
                });
            }
        });
    }
    
    function updateProgress(message) {
        $('.progress-message').text(message);
    }
    
    function updateProgressBar(percentage) {
        percentage = Math.min(100, percentage);
        $('.progress-bar-fill').css('width', percentage + '%');
        $('.progress-percentage').text(percentage + '%');
    }
    
    function showSuccess(data) {
        isExporting = false;
        $('#start-export').prop('disabled', false).text('Start Export');
        
        updateProgressBar(100);
        updateProgress('Export completed successfully!');
        
        // Build file list
        let fileList = '';
        if (data.files) {
            for (const [key, url] of Object.entries(data.files)) {
                fileList += `<li><a href="${url}" target="_blank">${url}</a></li>`;
            }
        }
        
        $('.export-urls').html(fileList);
        $('.export-success').slideDown();
        
        // Reload page after 5 seconds to show new export files
        setTimeout(function() {
            location.reload();
        }, 5000);
    }
    
    function handleError(message) {
        isExporting = false;
        $('#start-export').prop('disabled', false).text('Start Export');
        
        $('.error-message').text(message);
        $('.export-error').slideDown();
        $('.export-progress').slideUp();
    }
    
    function checkRequirements() {
        $('#requirements-results').slideDown();
        const $tbody = $('#requirements-table-body');
        $tbody.html('<tr><td colspan="4">Checking requirements...</td></tr>');
        
        $.ajax({
            url: reign_demo_exporter.ajax_url,
            type: 'POST',
            data: {
                action: 'reign_demo_check_requirements',
                nonce: reign_demo_exporter.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayRequirements(response.data);
                } else {
                    $tbody.html('<tr><td colspan="4">Failed to check requirements</td></tr>');
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="4">Error checking requirements</td></tr>');
            }
        });
    }
    
    function displayRequirements(requirements) {
        const $tbody = $('#requirements-table-body');
        let html = '';
        
        for (const [key, data] of Object.entries(requirements)) {
            const statusClass = data.status ? 'success' : 'error';
            const statusIcon = data.status ? '✓' : '✗';
            
            html += `
                <tr>
                    <td>${data.label}</td>
                    <td>${data.required}</td>
                    <td>${data.current}</td>
                    <td class="${statusClass}">${statusIcon}</td>
                </tr>
            `;
        }
        
        $tbody.html(html);
    }
    
    // Prevent page unload during export
    $(window).on('beforeunload', function() {
        if (isExporting) {
            return 'Export is in progress. Are you sure you want to leave?';
        }
    });
});