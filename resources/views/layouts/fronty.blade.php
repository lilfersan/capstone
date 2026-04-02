<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>AI Job Analyzer</title>

    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
    <link href="{{ asset('css/styles.css') }}" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>

    <style>
        /* Gemini Sidebar Styling */
        .sb-sidenav-dark {
            background-color: #1e1f20 !important;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .nav-link {
            color: #e3e3e3 !important;
            border-radius: 50px !important; /* Gemini pill shape */
            margin: 0 12px 4px 12px;
            transition: 0.2s;
        }

        .nav-link:hover {
            background-color: #2d2e30 !important;
        }

        /* Light Mode Styles */
        body.light-mode {
            background-color: #f8f9fa;
        }
        body.light-mode .sb-sidenav-dark {
            background-color: #f0f4f9 !important;
        }
        body.light-mode .nav-link {
            color: #444746 !important;
        }
        body.light-mode .nav-link:hover {
            background-color: #dde3ea !important;
        }
        body.light-mode .sb-sidenav-menu-heading {
            color: #444746 !important;
        }
        
        /* Sticky Sidebar Fix */
        #layoutSidenav_nav {
            z-index: 1038;
            width: 280px;
        }
    </style>
</head>

<body class="sb-nav-fixed">

    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <a class="navbar-brand ps-3" href="#">Gemini Analyzer</a>
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        


        <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown"><i class="fas fa-user fa-fw"></i></a>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="navbarDropdown">
                    <li><a class="dropdown-item" href="#">Profile</a></li>
                    <li><hr class="dropdown-divider" /></li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button class="dropdown-item">Logout</button>
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>

    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu flex-grow-1">
                    <div class="nav mt-3">
                        


                        <div class="sb-sidenav-menu-heading opacity-50 small text-uppercase px-4 mt-4">History</div>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <ul id="historyBody" class="list-unstyled" style="max-height: 300px;">
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="sb-sidenav-footer bg-transparent border-top border-secondary p-2">
                    <div class="dropup">
                        <button class="btn btn-link nav-link text-light w-100 text-start dropdown-toggle border-0" 
                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cog me-2"></i> Settings & help
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark shadow mb-2">
                            <li>
                                <button class="dropdown-item d-flex justify-content-between align-items-center py-2" onclick="toggleTheme()">
                                    <span><i class="fas fa-adjust me-2"></i> Theme</span>
                                    <span id="themeLabel" class="badge bg-secondary">Dark</span>
                                </button>
                            </li>
                            <li><a class="dropdown-item py-2" href="#"><i class="fas fa-history me-2"></i> Activity</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>

        <div id="layoutSidenav_content">
    <main>
        <div class="container-fluid px-4">
            <h1 class="mt-4 fw-bold">AI Job Post URL Analyzer</h1>
            <p class="text-muted mb-4">Analyze suspicious job links for potential scams</p>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-end-0 text-primary"><i class="fas fa-link"></i></span>
                        <input type="text" id="jobUrlInput" class="form-control border-start-0" placeholder="https://example-job-link.com/post/123">
                        <button class="btn btn-primary px-4" type="button" id="analyzeBtn" onclick="analyzeUrl()">Analyze URL</button>
                    </div>
                </div>
            </div>

            <div id="analysisResults" style="display: none;">
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <h5 class="card-title mb-4" id="resultTitle">URL Analysis Results</h5>
                        
                        <div class="gauge-container mx-auto position-relative" style="width: 250px; height: 125px; overflow: hidden;">
                            <div id="gaugeBody" class="gauge-body" style="width: 100%; height: 200%; border-radius: 50%; border: 35px solid #f0f0f0; border-top-color: #f1c40f; transform: rotate(45deg);"></div>
                            <div class="gauge-text position-absolute w-100" style="bottom: 5px; left: 0;">
                                <span class="display-6 fw-bold" id="riskPercent">0%</span>
                                <div class="fw-bold text-muted small text-uppercase" id="riskStatus">Processing</div>
                            </div>
                        </div>
                        
                        <p class="mt-4 text-muted mx-auto" style="max-width: 500px;" id="aiMessage">
                            The AI model is evaluating the indicators...
                        </p>
                    </div>
                </div>

                <h4 class="mb-3 mt-5"><i class="fas fa-list-ul me-2 text-primary"></i> Detailed Findings</h4>
                <div id="redFlagsContainer" class="list-group list-group-flush">
                </div>
            </div>

        </div>
    </main>
</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/scripts.js') }}"></script>

    <script>
    // 1. THEME TOGGLE LOGIC (Existing)
    function toggleTheme() {
        const body = document.body;
        const label = document.getElementById('themeLabel');
        if (body.classList.contains('light-mode')) {
            body.classList.remove('light-mode');
            localStorage.setItem('theme', 'dark');
            if(label) label.innerText = 'Dark';
        } else {
            body.classList.add('light-mode');
            localStorage.setItem('theme', 'light');
            if(label) label.innerText = 'Light';
        }
    }

    // 2. HISTORY RENDER LOGIC (New)
    function renderHistory() {
        const history = JSON.parse(localStorage.getItem('scanHistory') || '[]');
        const container = document.getElementById('historyBody');
        
        if (!container) return; // Safety check

        container.innerHTML = history.map(item => `
            <li class="p-2 border-bottom small">
                <div class="d-flex justify-content-between">
                    <div class="text-truncate" style="max-width: 70%;">${item.url}</div>
                    <div>
                        <span class="badge bg-${item.score >= 70 ? 'danger' : item.score >= 40 ? 'warning' : 'success'}">${item.status}</span>
                        <span class="ms-2">${item.score}%</span>
                        <small class="d-block text-muted">${item.date}</small>
                    </div>
                </div>
            </li>
        `).join('');
    }

    // 3. MAIN ANALYZE LOGIC (Updated to save history)
    async function analyzeUrl() {
        const url = document.getElementById('jobUrlInput').value;
        const btn = document.getElementById('analyzeBtn');
        
        if (!url) { alert("Please enter a URL"); return; }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Analyzing...';

        try {
            const response = await fetch('/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ url: url })
            });

            const data = await response.json();

            // Show results UI
            document.getElementById('analysisResults').style.display = 'block';
            document.getElementById('riskPercent').innerText = data.score + "%";
            document.getElementById('riskStatus').innerText = data.status;
            document.getElementById('aiMessage').innerText = data.explain || data.message || 'Analysis complete.';
            
            // Populate Detailed Findings
            const flagsContainer = document.getElementById('redFlagsContainer');
            if (data.keywords && data.keywords.length > 0) {
                flagsContainer.innerHTML = data.keywords.map(kw => `
                    <div class="list-group-item px-0 py-3 d-flex align-items-start border-bottom">
                        <i class="fas fa-exclamation-triangle text-warning mt-1 me-3"></i>
                        <div>
                            <h6 class="mb-1 text-capitalize fw-bold">${kw}</h6>
                            <p class="mb-0 text-muted small">This keyword is highly associated with scam job postings in our dataset.</p>
                        </div>
                    </div>
                `).join('');
            } else {
                flagsContainer.innerHTML = '<div class="list-group-item text-muted border-0 bg-transparent px-0"><i class="fas fa-check-circle text-success me-2"></i> No specific high-risk keywords detected.</div>';
            }

            // Gauge Color Logic
            const gauge = document.getElementById('gaugeBody');
            let statusColor = data.score >= 70 ? "#e74c3c" : (data.score >= 40 ? "#f1c40f" : "#2ecc71");
            gauge.style.borderTopColor = statusColor;

            // SAVE TO HISTORY
            const historyItem = {
                url: url,
                status: data.status,
                score: data.score,
                date: new Date().toLocaleTimeString()
            };
            let history = JSON.parse(localStorage.getItem('scanHistory') || '[]');
            history.unshift(historyItem); 
            localStorage.setItem('scanHistory', JSON.stringify(history.slice(0, 5))); 
            renderHistory();

        } catch (error) {
            alert("Error: Is your Python terminal running?");
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Analyze URL';
        }
    }

    // 4. RUN ON PAGE LOAD
    document.addEventListener('DOMContentLoaded', function() {
        renderHistory();
        
        // Apply saved theme
        if (localStorage.getItem('theme') === 'light') {
            document.body.classList.add('light-mode');
            const label = document.getElementById('themeLabel');
            if(label) label.innerText = 'Light';
        }
    });
</script>
</body>
</html>