<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>AI Job Analyzer | Cyber Security Tool</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-[#070b14] text-slate-300 font-sans antialiased min-h-screen flex selection:bg-cyan-500/30">

    <!-- Sidebar -->
    <aside class="w-72 fixed inset-y-0 left-0 bg-[#0b1120] border-r border-[#1e293b] flex flex-col z-20 transition-transform shadow-[4px_0_24px_rgba(0,0,0,0.5)]">
       <div class="px-6 py-8">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center shadow-[0_0_15px_rgba(34,211,238,0.4)]">
                    <i class="fas fa-shield-halved text-white text-lg"></i>
                </div>
                <h1 class="text-sm md:text-base font-bold tracking-wide text-white font-['Outfit'] leading-tight">Online Fake Job Posting <br><span class="text-cyan-400">Detection</span></h1>
            </div>
       </div>

       <div class="px-4 pb-2">
           <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest px-2 mb-2">History Logs</p>
       </div>
       <div class="flex-1 overflow-y-auto px-3 space-y-2 custom-scrollbar" id="historyBody">
           <!-- History items JS injection -->
       </div>

       <div class="p-4 border-t border-[#1e293b]">
           <form action="{{ route('logout') }}" method="POST">
               @csrf
               <button class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors group">
                   <i class="fas fa-sign-out-alt group-hover:text-cyan-400 transition-colors"></i>
                   <span class="font-medium text-sm">Secure Logout</span>
               </button>
           </form>
       </div>
    </aside>

    <!-- Main Content -->
    <main class="ml-72 flex-1 flex flex-col min-h-screen relative overflow-hidden">
        <!-- Abstract glowing background element -->
        <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] bg-blue-600/20 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-[-20%] right-[-10%] w-[40%] h-[40%] bg-cyan-600/10 rounded-full blur-[100px] pointer-events-none"></div>

        <div class="flex-1 overflow-y-auto p-8 md:p-12 z-10 relative">
            <div class="max-w-4xl mx-auto mt-8">
                
                <!-- Main Header -->
                <div class="mb-12">
                    <h2 class="text-4xl md:text-5xl font-extrabold text-white mb-4 tracking-tight">Online Fake Job Posting <span class="text-gradient">Detection</span></h2>
                    <p class="text-lg text-slate-400">Deploy advanced NLP models to detect fraudulent hiring campaigns in real time.</p>
                </div>

                <!-- Input section glass panel -->
                <div class="glass-panel p-2 rounded-2xl mb-12 flex flex-col md:flex-row gap-2 relative z-10">
                    <div class="relative flex-1 flex items-center">
                        <i class="fas fa-link absolute left-5 text-slate-400 text-lg"></i>
                        <input type="url" id="jobUrlInput" class="w-full bg-transparent border-none text-white text-lg py-4 pl-14 pr-4 focus:ring-0 placeholder:text-slate-600 font-medium outline-none" placeholder="https://company.com/careers/job-1234">
                    </div>
                    <button id="analyzeBtn" onclick="analyzeUrl()" class="bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-bold py-4 px-8 rounded-xl transition-all shadow-[0_0_20px_rgba(6,182,212,0.4)] hover:shadow-[0_0_30px_rgba(6,182,212,0.6)] flex items-center justify-center gap-2 group">
                        <span>Analyze Target</span> <i class="fas fa-radar group-hover:animate-spin"></i>
                    </button>
                </div>

                <!-- AI Results Section (Hidden by default) -->
                <div id="analysisResults" class="hidden animate-fade-in-up">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                        
                        <!-- Primary Threat Gauge -->
                        <div class="glass-panel-heavy rounded-2xl p-8 col-span-1 md:col-span-2 flex flex-col items-center justify-center text-center relative overflow-hidden">
                            <!-- Background glow attached to status -->
                            <div id="statusGlow" class="absolute inset-0 opacity-20 blur-xl transition-colors duration-1000 bg-cyan-500"></div>

                            <p class="text-xs font-bold text-slate-400 tracking-[0.2em] mb-6 uppercase z-10">Threat Assessment</p>
                            
                            <div class="relative w-48 h-24 overflow-hidden mb-6 z-10">
                                <div class="absolute inset-0 rounded-t-full border-[20px] border-slate-800 border-b-0"></div>
                                <!-- Rotate this from -180deg (0%) to 0deg (100%) -->
                                <div id="gaugeBody" class="absolute inset-0 rounded-t-full border-[20px] border-cyan-500 border-b-0 origin-bottom transition-transform duration-1000 ease-out" style="transform: rotate(-180deg);"></div>
                            </div>
                            
                            <div class="z-10 mt-[-35px]">
                                <h3 id="riskPercent" class="text-6xl font-black text-white tracking-tighter mb-1 font-['Outfit']">0%</h3>
                                <div id="riskStatus" class="inline-block px-3 py-1 rounded bg-cyan-500/20 text-cyan-400 text-sm font-bold uppercase tracking-wider border border-cyan-500/30">Safe</div>
                            </div>
                        </div>

                        <!-- Explainer Box -->
                        <div class="glass-panel rounded-2xl p-8 col-span-1 md:col-span-3 flex flex-col justify-center">
                            <h4 class="text-xl font-bold text-white mb-2 flex items-center gap-2"><i class="fas fa-microchip text-blue-400"></i> Neural Network Conclusion</h4>
                            <p id="aiMessage" class="text-slate-300 text-lg leading-relaxed mb-6 font-light">The AI model is evaluating the indicators...</p>
                            
                            <!-- Detailed findings logic -->
                            <div class="mt-auto">
                                <h5 id="flagsHeader" class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4 border-b border-white/10 pb-2">Identified Vulnerabilities / Keywords</h5>
                                <div id="redFlagsContainer" class="flex flex-wrap gap-2">
                                    <!-- injected tags -->
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Custom CSS strictly for animations and scrollbars -->
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        .animate-fade-in-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <script>
        // HISTORY RENDERING
        function renderHistory() {
            const history = JSON.parse(localStorage.getItem('scanHistory') || '[]');
            const container = document.getElementById('historyBody');
            if (!container) return;

            container.innerHTML = history.map(item => {
                let colorClass = item.score >= 70 ? 'text-rose-400 bg-rose-500/10 border-rose-500/20' : 
                                 (item.score >= 40 ? 'text-amber-400 bg-amber-500/10 border-amber-500/20' : 
                                 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20');
                
                return `
                <div class="p-3 rounded-lg border border-white/5 bg-white/[0.02] hover:bg-white/[0.05] transition-colors mb-2 cursor-pointer flex flex-col" onclick="document.getElementById('jobUrlInput').value='${item.url}'">
                    <div class="text-xs text-slate-300 truncate w-full opacity-80 mb-2 font-mono">${item.url}</div>
                    <div class="flex justify-between items-center w-full">
                        <span class="text-xs font-bold px-2 py-0.5 rounded border ${colorClass} uppercase tracking-wider">${item.status}</span>
                        <span class="text-xs text-slate-500">${item.date}</span>
                    </div>
                </div>
                `;
            }).join('');
        }

        // SCAN LOGIC
        async function analyzeUrl() {
            const url = document.getElementById('jobUrlInput').value;
            const btn = document.getElementById('analyzeBtn');
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            if (!url) { alert("Please enter a Target URL."); return; }

            // UI Loading state
            const origHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="animate-spin text-lg"><i class="fas fa-circle-notch"></i></span> <span>Processing...</span>`;
            document.getElementById('analysisResults').classList.add('hidden');

            try {
                const response = await fetch('/scan', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ url: url })
                });

                const data = await response.json();
                
                // Show Panel
                document.getElementById('analysisResults').classList.remove('hidden');

                // Error Handling for Blocked/Failed Scrapes
                if (data.status === 'Error' || data.error) {
                    document.getElementById('riskPercent').innerText = "ERR";
                    const statusBadge = document.getElementById('riskStatus');
                    statusBadge.innerText = "Blocked";
                    statusBadge.className = "inline-block px-3 py-1 rounded text-sm font-bold uppercase tracking-wider border bg-rose-500/20 text-rose-400 border-rose-500/30";
                    document.getElementById('aiMessage').innerText = data.message || data.error || "Could not read text from this link. Anti-bot protection blocked the scraper.";
                    
                    const gaugeBody = document.getElementById('gaugeBody');
                    gaugeBody.style.transform = "rotate(-180deg)";
                    gaugeBody.style.borderTopColor = "#fb7185";
                    gaugeBody.style.borderRightColor = "#fb7185";
                    gaugeBody.style.borderLeftColor = "#fb7185";
                    
                    document.getElementById('statusGlow').className = "absolute inset-0 opacity-20 blur-2xl transition-colors duration-1000 bg-rose-600";
                    
                    document.getElementById('flagsHeader').innerText = "Scan Failed";
                    document.getElementById('redFlagsContainer').innerHTML = '<span class="text-sm text-rose-400 italic"><i class="fas fa-ban me-2"></i>Target blocked the scanner or is unreachable.</span>';
                    return; // Stop further execution
                }

                // Color mapping
                const isHigh = data.score >= 70;
                const isMed = data.score >= 40 && data.score < 70;
                
                const colorHex = isHigh ? "#fb7185" : (isMed ? "#fbbf24" : "#22d3ee"); 
                const bgClass = isHigh ? "bg-rose-500/20 text-rose-400 border-rose-500/30" : 
                               (isMed ? "bg-amber-500/20 text-amber-400 border-amber-500/30" : 
                               "bg-cyan-500/20 text-cyan-400 border-cyan-500/30");
                const glowColor = isHigh ? "bg-rose-600" : (isMed ? "bg-amber-600" : "bg-cyan-600");

                // Update text
                document.getElementById('riskPercent').innerText = data.score + "%";
                const statusBadge = document.getElementById('riskStatus');
                statusBadge.innerText = data.status;
                statusBadge.className = `inline-block px-3 py-1 rounded text-sm font-bold uppercase tracking-wider border ${bgClass}`;
                document.getElementById('aiMessage').innerText = data.explain || data.message || 'Analysis Complete. System clear.';
                
                // Animate Gauge & Background
                // Gauge rotation formula: -180deg (0%) to 0deg (100%)
                const rotation = -180 + (data.score * 1.8);
                const gaugeBody = document.getElementById('gaugeBody');
                gaugeBody.style.transform = `rotate(${rotation}deg)`;
                
                // Adjust border colors without overwriting border-b-0
                gaugeBody.style.borderTopColor = colorHex;
                gaugeBody.style.borderRightColor = colorHex;
                gaugeBody.style.borderLeftColor = colorHex;
                
                document.getElementById('statusGlow').className = `absolute inset-0 opacity-20 blur-2xl transition-colors duration-1000 ${glowColor}`;

                // Populating red flags
                const flagsContainer = document.getElementById('redFlagsContainer');
                const flagsHeader = document.getElementById('flagsHeader');
                
                if (isHigh || isMed) {
                    flagsHeader.innerText = "Identified Vulnerabilities / Keywords";
                    flagsHeader.className = "text-sm font-semibold text-amber-400 uppercase tracking-wider mb-4 border-b border-white/10 pb-2";
                } else {
                    flagsHeader.innerText = "Key Authentic Phrases";
                    flagsHeader.className = "text-sm font-semibold text-cyan-400 uppercase tracking-wider mb-4 border-b border-white/10 pb-2";
                }

                if (data.keywords && data.keywords.length > 0) {
                    flagsContainer.innerHTML = data.keywords.map(kw => {
                        const iconHtml = isHigh ? '<i class="fas fa-solid fa-radiation text-rose-400 text-xs"></i>' : 
                                         (isMed ? '<i class="fas fa-exclamation-triangle text-amber-400 text-xs"></i>' : 
                                         '<i class="fas fa-check-circle text-cyan-400 text-xs"></i>');
                        return `
                        <div class="flex items-center gap-2 bg-white/5 border border-white/10 px-3 py-1.5 rounded-full shadow-lg">
                            ${iconHtml}
                            <span class="text-sm font-medium text-slate-200 capitalize">${kw}</span>
                        </div>
                        `;
                    }).join('');
                } else {
                    flagsContainer.innerHTML = '<span class="text-sm text-slate-400 italic"><i class="fas fa-check-circle text-cyan-400 me-2"></i> No specific threat indicators found.</span>';
                }

                // Append History
                const historyItem = { url: url, status: data.status, score: data.score, date: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) };
                let history = JSON.parse(localStorage.getItem('scanHistory') || '[]');
                history.unshift(historyItem); 
                localStorage.setItem('scanHistory', JSON.stringify(history.slice(0, 8))); 
                renderHistory();

            } catch (error) {
                alert("Network Exception: Is your Python security service running?");
                console.error(error);
            } finally {
                btn.disabled = false;
                btn.innerHTML = origHTML;
            }
        }

        // INIT
        document.addEventListener('DOMContentLoaded', renderHistory);
    </script>
</body>
</html>