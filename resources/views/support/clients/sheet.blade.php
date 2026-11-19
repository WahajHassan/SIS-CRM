@extends('layouts.admin')

@section('content')
<!-- Include Jspreadsheet CE -->
<script src="https://bossanova.uk/jspreadsheet/v4/jexcel.js"></script>
<link rel="stylesheet" href="https://bossanova.uk/jspreadsheet/v4/jexcel.css" type="text/css" />
<script src="https://jsuites.net/v4/jsuites.js"></script>
<link rel="stylesheet" href="https://jsuites.net/v4/jsuites.css" type="text/css" />

<div class="page-title" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 style="font-size: 2rem; font-weight: 700; color: var(--text-main); margin-bottom: 5px;">Google Sheet View</h1>
        <p style="color: var(--text-muted);">Manage all clients in a spreadsheet interface. Double-click any cell to edit.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button id="downloadSheetBtn" class="btn btn-ghost" style="padding: 0.8rem 1.5rem; font-weight: 600; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main);">
            <i class="fas fa-file-download"></i> Download CSV
        </button>
        <button id="saveSheetBtn" class="btn btn-primary" style="padding: 0.8rem 1.5rem; font-weight: 600; border-radius: 12px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);">
            <i class="fas fa-save"></i> Save Changes
        </button>
    </div>
</div>

<div class="sys-card sheet-container" style="background: var(--bg-card); border-radius: 16px; padding: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid var(--border); overflow-x: auto; overflow-y: hidden; width: 100%;">
    <div id="spreadsheet" style="width: 100%;"></div>
</div>

@php
    $formattedClients = $clients->map(function($c) {
        return [
            $c->id,
            $c->name,
            $c->business_owner_name,
            $c->business_email,
            $c->business_number,
            $c->client_status,
            $c->status,
            $c->value
        ];
    })->values();
@endphp
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const clientsData = @json($formattedClients);

        const spreadsheet = jspreadsheet(document.getElementById('spreadsheet'), {
            data: clientsData,
            columns: [
                { type: 'text', title: 'ID', width: 50, readOnly: true },
                { type: 'text', title: 'Deal Name', width: 220 },
                { type: 'text', title: 'Owner Name', width: 180 },
                { type: 'text', title: 'Business Email', width: 220 },
                { type: 'text', title: 'Business Number', width: 150 },
                { type: 'dropdown', title: 'Client Status', width: 160, source: [
                    {id: 'active', name: 'Active Customers'},
                    {id: 'on-hold', name: 'On Hold Clients'},
                    {id: 'charge-back', name: 'Charge Back Clients'},
                    {id: 'case-studies', name: 'Case Studies'},
                    {id: 'deactivated', name: 'Deactivated Customers'},
                    {id: 'win-back', name: 'Win Back Customers'},
                    {id: 'dispatched', name: 'Dispatched Sales'},
                    {id: 'pending', name: 'Pending Sales'},
                    {id: 'sales-on-hold', name: 'On Hold (Sales)'}
                ] },
                { type: 'dropdown', title: 'Deal Status', width: 120, source: [
                    {id: 'WON', name: 'WON'},
                    {id: 'LOST', name: 'LOST'},
                    {id: 'OPEN', name: 'OPEN'}
                ] },
                { type: 'numeric', title: 'Value', width: 120, mask: '$ #.##,00' },
            ],
            search: true,
            pagination: 20,
            allowExport: true,
            wordWrap: true,
            csvFileName: 'Clients_Data',
            csvHeaders: true
        });

        // Download CSV Button
        document.getElementById('downloadSheetBtn').addEventListener('click', function() {
            spreadsheet.download(true);
        });

        document.getElementById('saveSheetBtn').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;

            const currentData = spreadsheet.getData();
            
            const payload = currentData.map(row => {
                return {
                    id: row[0],
                    name: row[1],
                    business_owner_name: row[2],
                    business_email: row[3],
                    business_number: row[4],
                    client_status: row[5],
                    status: row[6],
                    value: row[7]
                };
            });

            fetch('{{ route("support.sheet.save") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ data: payload })
            })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                if(data.success) {
                    Swal.fire('Success', 'Data saved successfully!', 'success');
                } else {
                    Swal.fire('Error', 'Failed to save data.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                btn.innerHTML = originalText;
                btn.disabled = false;
                Swal.fire('Error', 'An error occurred while saving.', 'error');
            });
        });
    });
</script>

<style>
    /* Premium Jspreadsheet UI Overrides */
    .sheet-wrapper {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        padding-bottom: 150px;
    }
    .sheet-container {
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 100%;
    }
    .jexcel_content { 
        box-shadow: none !important; 
        border: none !important; 
        max-width: 100%;
        padding-bottom: 150px !important;
    }
    
    /* Search Bar Styling */
    .jexcel_filter {
        padding: 1rem 1.5rem;
        background: var(--bg-card);
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        align-items: center;
    }
    .jexcel_filter input {
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 0.6rem 1rem;
        font-family: inherit;
        font-size: 0.9rem;
        width: 250px;
        transition: all 0.2s ease;
        background: var(--bg-body);
        color: var(--text-main);
    }
    .jexcel_filter input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(249, 157, 78, 0.15);
    }

    /* Table Headers */
    .jexcel > thead > tr > td { 
        background-color: var(--bg-body); 
        color: var(--text-muted); 
        font-weight: 600; 
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 15px 10px; 
        border-bottom: 2px solid var(--border);
        border-right: 1px solid var(--border);
        border-top: none;
        border-left: none;
    }
    
    /* Table Cells */
    .jexcel > tbody > tr > td { 
        padding: 12px 10px; 
        color: var(--text-main); 
        font-size: 0.95rem;
        border-bottom: 1px solid var(--border); 
        border-right: 1px solid var(--border); 
        border-top: none;
        border-left: none;
    }
    
    /* Zebra Striping */
    .jexcel > tbody > tr:nth-child(even) { background-color: rgba(0,0,0,0.01); }
    .jexcel > tbody > tr:hover { background-color: rgba(249, 157, 78, 0.05); }

    /* Active Cell */
    .jexcel .highlight { background-color: rgba(249, 157, 78, 0.1) !important; }
    
    /* Pagination */
    .jexcel_pagination { 
        padding: 1rem 1.5rem; 
        border-top: 1px solid var(--border); 
        background: var(--bg-card);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .jexcel_pagination > div > div {
        cursor: pointer;
        padding: 6px 12px;
        border-radius: 6px;
        margin: 0 2px;
        transition: all 0.2s;
    }
    .jexcel_pagination > div > div:hover {
        background: rgba(249, 157, 78, 0.1);
        color: var(--primary);
    }
    .jexcel_page_selected {
        background: var(--primary) !important;
        color: white !important;
        font-weight: 600;
    }

    /* Dark Mode Support */
    html.dark-mode .jexcel_filter input { background-color: #1a2235; border-color: #2d3748; color: #fff; }
    html.dark-mode .jexcel > thead > tr > td { background-color: #1a2235; color: #a0aec0; border-color: #2d3748; }
    html.dark-mode .jexcel > tbody > tr > td { background-color: transparent; border-color: #2d3748; color: #e2e8f0; }
    html.dark-mode .jexcel > tbody > tr:nth-child(even) { background-color: rgba(255,255,255,0.02); }
    html.dark-mode .jexcel > tbody > tr:hover { background-color: rgba(255,255,255,0.05); }
    html.dark-mode .jexcel_pagination { border-color: #2d3748; }
</style>
@endsection
