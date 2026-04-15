
        const SCAM_PHRASE_LIBRARY_SIZE = 10;
        const KNOWN_JOB_BOARDS = ['jobstreet', 'indeed', 'linkedin', 'kalibrr', 'jobsdb', 'monster', 'glassdoor', 'seek'];
        const STATUS_THEME = {
            safe: {
                badge: 'SAFE',
                label: 'Safe',
                ringColor: '#34d399',
                tone: 'safe',
                glow: 'bg-emerald-500',
                summary: 'Trusted Source',
                actionIcon: 'fa-check',
            },
            caution: {
                badge: 'CAUTION',
                label: 'Caution',
                ringColor: '#fbbf24',
                tone: 'caution',
                glow: 'bg-amber-500',
                summary: 'Needs Review',
                actionIcon: 'fa-triangle-exclamation',
            },
            fraud: {
                badge: 'FRAUD',
                label: 'Fraud',
                ringColor: '#fb7185',
                tone: 'fraud',
                glow: 'bg-rose-500',
                summary: 'High Threat',
                actionIcon: 'fa-ban',
            },
            error: {
                badge: 'BLOCKED',
                label: 'Blocked',
                ringColor: '#fb7185',
                tone: 'error',
                glow: 'bg-rose-600',
                summary: 'Scan Failed',
                actionIcon: 'fa-circle-xmark',
            },
        };

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function toArray(value) {
            return Array.isArray(value) ? value.filter(Boolean) : [];
        }

        function toNumber(value, fallback = 0) {
            const num = Number(value);
            return Number.isFinite(num) ? num : fallback;
        }

        function clamp(value, min = 0, max = 100) {
            return Math.min(max, Math.max(min, value));
        }

        function getHostname(value) {
            try {
                return new URL(value).hostname || '';
            } catch (error) {
                return '';
            }
        }

        function isRecognizedBoard(hostname) {
            const host = String(hostname || '').toLowerCase();
            return KNOWN_JOB_BOARDS.some((item) => host.includes(item));
        }

        function formatYears(years) {
            if (!Number.isFinite(years) || years <= 0) return 'Unknown';
            if (years < 1) return `${Math.max(1, Math.round(years * 365))} days`;

            const rounded = years >= 10 ? years.toFixed(1) : years.toFixed(2);
            const clean = rounded.replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
            const estimatedYear = new Date().getFullYear() - Math.floor(years);
            return `${clean} years (est. ${estimatedYear})`;
        }

        function formatPercent(value) {
            return `${clamp(Math.round(toNumber(value, 0)))}%`;
        }

        function prettyContactType(value) {
            const contact = String(value || 'unknown').toLowerCase();
            if (contact === 'corporate_email') return 'Corporate Email';
            if (contact === 'personal_email') return 'Personal Email';
            return 'Not Detected';
        }

        function toneForScore(value) {
            const score = toNumber(value, 0);
            if (score >= 61) return 'danger';
            if (score >= 31) return 'warn';
            return 'safe';
        }

        function fillClassForTone(tone) {
            if (tone === 'danger') return 'bg-gradient-to-r from-rose-500 to-red-500';
            if (tone === 'warn') return 'bg-gradient-to-r from-amber-400 to-yellow-500';
            return 'bg-gradient-to-r from-emerald-400 to-cyan-400';
        }

        function toneClassForText(tone) {
            if (tone === 'danger') return 'text-rose-300';
            if (tone === 'warn') return 'text-amber-300';
            return 'text-emerald-300';
        }

        function dotToneClass(tone) {
            if (tone === 'danger') return 'bg-rose-400';
            if (tone === 'warn') return 'bg-amber-400';
            if (tone === 'muted') return 'bg-slate-400';
            return 'bg-emerald-400';
        }

        function pushUnique(list, value) {
            const trimmed = String(value || '').trim();
            if (!trimmed) return;
            if (!list.includes(trimmed)) list.push(trimmed);
        }

        function deriveVerdict(data, score) {
            const raw = String(
                data?.verdict
                ?? data?.classification
                ?? data?.final_risk_score?.level
                ?? data?.status
                ?? ''
            ).toLowerCase();

            if (raw.includes('error') || data?.error) return 'error';
            if (raw.includes('fraud') || raw.includes('high')) return 'fraud';
            if (raw.includes('caution') || raw.includes('suspicious') || raw.includes('medium')) return 'caution';
            return score >= 70 ? 'fraud' : (score >= 40 ? 'caution' : 'safe');
        }

        function parseConfidence(data) {
            const candidates = [
                data?.confidence_pct,
                data?.final_risk_score?.confidence,
            ];

            for (const item of candidates) {
                if (item === null || item === undefined || item === '') continue;
                let num = Number(item);
                if (!Number.isFinite(num)) continue;
                if (num <= 1) num *= 100;
                return clamp(Math.round(num));
            }

            return 0;
        }

        function createSafeNarrative(model) {
            const primary = model.recommendedAction || 'Safe to apply. Verify company email before submitting personal data.';
            return `${primary} Current signals lean legitimate based on domain trust, content quality, and the absence of strong scam phrase matches.`;
        }

        function createCautionNarrative(model) {
            const primary = model.recommendedAction || 'Proceed with caution. Verify the company independently before applying.';
            return `${primary} The posting is not an automatic scam, but some indicators still need manual review before you share documents or continue.`;
        }

        function createFraudNarrative(model) {
            const primary = model.recommendedAction || 'Do not apply. Multiple fraud indicators detected.';
            return `${primary} Several indicators across the URL, content, or application flow suggest a potentially fraudulent hiring attempt.`;
        }

        function createErrorNarrative(message) {
            return message || 'The scanner could not fully analyze this target. It may be unreachable, blocked, or protected by anti-bot measures.';
        }

        function normalizeAnalysis(data, requestedUrl = '') {
            const score = clamp(Math.round(
                toNumber(
                    data?.risk_score
                    ?? data?.overall_threat_score
                    ?? data?.score
                    ?? data?.threat_score
                    ?? data?.final_risk_score?.score,
                    0
                )
            ));

            const verdictKey = deriveVerdict(data, score);
            const theme = STATUS_THEME[verdictKey];
            const confidencePct = parseConfidence(data);
            const sourceUrl = String(data?.url || requestedUrl || '');
            const hostname = getHostname(sourceUrl);
            const recognizedBoard = isRecognizedBoard(hostname);
            const domainAgeYears = Number.isFinite(Number(data?.domain_age_years))
                ? Number(data.domain_age_years)
                : null;
            const sslValid = Boolean(data?.ssl_valid);
            const sslIssuer = String(data?.ssl_issuer || 'Unknown');
            const redirectCount = clamp(Math.round(toNumber(data?.redirect_count, 0)), 0, 25);
            const descWordCount = clamp(Math.round(toNumber(data?.desc_word_count, 0)), 0, 100000);
            const grammarScore = clamp(Math.round(toNumber(data?.grammar_score, 0)));
            const contactType = String(data?.contact_type || 'unknown').toLowerCase();
            const scamPhrases = toArray(data?.scam_phrases_matched || data?.key_phrases || data?.keywords);
            const recommendation = String(
                data?.recommended_action
                ?? data?.recommendation
                ?? data?.explain
                ?? data?.message
                ?? ''
            ).trim();

            const warningSignals = [];
            const authenticSignals = [];

            toArray(data?.warning_signals || data?.red_flags).forEach((item) => pushUnique(warningSignals, item));
            toArray(data?.authentic_signals || data?.green_signals).forEach((item) => pushUnique(authenticSignals, item));

            if (sslValid) pushUnique(authenticSignals, 'HTTPS / SSL certificate is active');
            else pushUnique(warningSignals, 'SSL / HTTPS validation failed');

            if (domainAgeYears !== null) {
                if (domainAgeYears >= 2) pushUnique(authenticSignals, `Domain age suggests an established source (${formatYears(domainAgeYears)})`);
                if (domainAgeYears < 1) pushUnique(warningSignals, 'Domain appears newly registered');
            } else {
                pushUnique(warningSignals, 'Domain age could not be verified');
            }

            if (redirectCount === 0) pushUnique(authenticSignals, 'No redirect anomalies detected');
            if (redirectCount > 1) pushUnique(warningSignals, `Redirect chain contains ${redirectCount} hops`);

            if (recognizedBoard) pushUnique(authenticSignals, 'Hostname matches a recognized job board');
            else if (hostname) pushUnique(warningSignals, 'Posting is hosted on an unrecognized source domain');

            if (descWordCount >= 80) pushUnique(authenticSignals, 'Detailed job description present');
            else pushUnique(warningSignals, 'Job description is shorter than expected');

            if (grammarScore >= 60) pushUnique(authenticSignals, 'Readable grammar quality');
            else pushUnique(warningSignals, 'Grammar quality is below the preferred threshold');

            if (contactType === 'corporate_email') pushUnique(authenticSignals, 'Uses a corporate email contact');
            if (contactType === 'personal_email') pushUnique(warningSignals, 'Uses a personal email contact');
            if (contactType === 'unknown') pushUnique(warningSignals, 'Contact channel is not clearly stated');

            if (scamPhrases.length === 0) pushUnique(authenticSignals, 'No known scam phrases detected');
            if (scamPhrases.length > 0) pushUnique(warningSignals, `Known scam phrase matches: ${scamPhrases.slice(0, 3).join(', ')}`);

            const whoisRegistrant = String(data?.whois_registrant || 'Unknown');
            if (whoisRegistrant.toLowerCase() !== 'unknown') {
                pushUnique(authenticSignals, `WHOIS registrant available (${whoisRegistrant})`);
            } else {
                pushUnique(warningSignals, 'WHOIS registrant could not be confirmed');
            }

            const urlRisk = clamp(Math.round(toNumber(data?.url_risk, sslValid ? 10 : 45)));
            const contentRisk = clamp(Math.round(toNumber(data?.content_risk, scamPhrases.length > 0 ? 50 : 20)));
            const companyRisk = clamp(Math.round(toNumber(data?.company_risk, whoisRegistrant.toLowerCase() === 'unknown' ? 55 : 25)));
            const salaryRisk = clamp(Math.round(toNumber(data?.salary_risk, 15)));
            const applicationRisk = clamp(Math.round(toNumber(data?.application_risk, 10)));

            const severePhraseSet = new Set([
                'processing fee',
                'send your id',
                'apply via gmail',
                'no interview',
                'earn per day',
                'guaranteed salary',
                'work from home earn',
            ]);
            const criticalFlags = scamPhrases.filter((item) => severePhraseSet.has(String(item).toLowerCase()));

            const subscoreCards = [
                {
                    label: 'URL / Domain Risk',
                    value: urlRisk,
                    note: sslValid
                        ? (redirectCount === 0 ? 'SSL active, direct path, no redirect anomaly' : `${redirectCount} redirect hop(s) observed`)
                        : 'HTTPS validation failed or is unavailable',
                },
                {
                    label: 'Content Quality Risk',
                    value: contentRisk,
                    note: scamPhrases.length > 0
                        ? `${scamPhrases.length} phrase match(es) triggered rule-based checks`
                        : (descWordCount < 80 ? 'Content sample is shorter than expected' : 'No strong phrase-based content warnings'),
                },
                {
                    label: 'Company Verification Risk',
                    value: companyRisk,
                    note: whoisRegistrant.toLowerCase() === 'unknown'
                        ? 'Registrant identity could not be verified from WHOIS'
                        : 'Registrant information is available to the analyzer',
                },
                {
                    label: 'Salary Realism Risk',
                    value: salaryRisk,
                    note: salaryRisk >= 50
                        ? 'Language suggests unrealistic compensation promises'
                        : 'No extreme salary promise was detected in rule checks',
                },
                {
                    label: 'Application Risk',
                    value: applicationRisk,
                    note: criticalFlags.length > 0
                        ? `Critical application signals: ${criticalFlags.slice(0, 2).join(', ')}`
                        : 'No fee, ID, or direct bypass signal was detected',
                },
            ];

            const urlIntelCards = [
                {
                    icon: 'fa-calendar-days',
                    label: 'Domain Age',
                    value: formatYears(domainAgeYears),
                    tone: domainAgeYears === null ? 'warn' : (domainAgeYears >= 2 ? 'safe' : 'warn'),
                },
                {
                    icon: 'fa-lock',
                    label: 'SSL Certificate',
                    value: sslValid ? `Valid - ${sslIssuer}` : 'Unavailable',
                    tone: sslValid ? 'safe' : 'danger',
                },
                {
                    icon: 'fa-id-card',
                    label: 'WHOIS Registrant',
                    value: whoisRegistrant,
                    tone: whoisRegistrant.toLowerCase() === 'unknown' ? 'warn' : 'safe',
                },
                {
                    icon: 'fa-shuffle',
                    label: 'Redirect Chain',
                    value: redirectCount === 0 ? '0 redirects - Direct' : `${redirectCount} redirect(s)`,
                    tone: redirectCount <= 1 ? 'safe' : 'warn',
                },
                {
                    icon: 'fa-signature',
                    label: 'Source Pattern',
                    value: recognizedBoard ? 'Recognized job board' : (hostname ? 'Custom / direct source' : 'Unknown source'),
                    tone: recognizedBoard ? 'safe' : 'muted',
                },
                {
                    icon: 'fa-envelope-open-text',
                    label: 'Contact Channel',
                    value: prettyContactType(contactType),
                    tone: contactType === 'corporate_email' ? 'safe' : (contactType === 'personal_email' ? 'danger' : 'warn'),
                },
            ];

            const verificationRows = [
                {
                    label: 'SSL / HTTPS',
                    value: sslValid ? 'VERIFIED' : 'MISSING',
                    tone: sslValid ? 'safe' : 'danger',
                },
                {
                    label: 'Source Reputation',
                    value: recognizedBoard ? 'RECOGNIZED JOB BOARD' : 'DIRECT / UNKNOWN SOURCE',
                    tone: recognizedBoard ? 'safe' : 'warn',
                },
                {
                    label: 'WHOIS Registrant',
                    value: whoisRegistrant.toLowerCase() === 'unknown' ? 'NOT DISCLOSED' : whoisRegistrant,
                    tone: whoisRegistrant.toLowerCase() === 'unknown' ? 'warn' : 'safe',
                },
                {
                    label: 'Contact Transparency',
                    value: prettyContactType(contactType).toUpperCase(),
                    tone: contactType === 'corporate_email' ? 'safe' : (contactType === 'personal_email' ? 'danger' : 'warn'),
                },
                {
                    label: 'Description Detail',
                    value: descWordCount >= 80 ? `${descWordCount} WORDS` : `${descWordCount} WORDS (BRIEF)`,
                    tone: descWordCount >= 80 ? 'safe' : 'warn',
                },
                {
                    label: 'Scam Phrase Hits',
                    value: scamPhrases.length === 0 ? 'NONE FOUND' : `${scamPhrases.length} MATCHED`,
                    tone: scamPhrases.length === 0 ? 'safe' : 'danger',
                },
            ];

            const contentChips = [
                {
                    label: descWordCount >= 80 ? 'Detailed Description' : 'Brief Description',
                    tone: descWordCount >= 80 ? 'safe' : 'warn',
                    icon: descWordCount >= 80 ? 'fa-check' : 'fa-triangle-exclamation',
                },
                {
                    label: grammarScore >= 70 ? 'Readable Grammar' : 'Grammar Needs Review',
                    tone: grammarScore >= 70 ? 'safe' : 'warn',
                    icon: grammarScore >= 70 ? 'fa-check' : 'fa-triangle-exclamation',
                },
                {
                    label: contactType === 'corporate_email'
                        ? 'Corporate Email'
                        : (contactType === 'personal_email' ? 'Personal Email' : 'Contact Not Detected'),
                    tone: contactType === 'corporate_email' ? 'safe' : (contactType === 'personal_email' ? 'danger' : 'warn'),
                    icon: contactType === 'corporate_email' ? 'fa-check' : 'fa-envelope',
                },
                {
                    label: scamPhrases.length === 0 ? 'No Scam Phrases' : 'Phrase Matches Found',
                    tone: scamPhrases.length === 0 ? 'safe' : 'danger',
                    icon: scamPhrases.length === 0 ? 'fa-check' : 'fa-radiation',
                },
                {
                    label: criticalFlags.some((item) => item.toLowerCase() === 'processing fee') ? 'Upfront Payment Signal' : 'No Upfront Payment',
                    tone: criticalFlags.some((item) => item.toLowerCase() === 'processing fee') ? 'danger' : 'safe',
                    icon: criticalFlags.some((item) => item.toLowerCase() === 'processing fee') ? 'fa-triangle-exclamation' : 'fa-check',
                },
                {
                    label: criticalFlags.some((item) => item.toLowerCase() === 'send your id') ? 'ID Request Found' : 'No ID Request',
                    tone: criticalFlags.some((item) => item.toLowerCase() === 'send your id') ? 'danger' : 'safe',
                    icon: criticalFlags.some((item) => item.toLowerCase() === 'send your id') ? 'fa-triangle-exclamation' : 'fa-check',
                },
            ];

            const contentRows = [
                {
                    label: 'Description Length',
                    value: `${descWordCount} words ${descWordCount >= 80 ? '(Detailed)' : '(Brief)'}`,
                },
                {
                    label: 'Grammar Score',
                    value: `${grammarScore} / 100`,
                },
                {
                    label: 'Contact Type',
                    value: prettyContactType(contactType),
                },
                {
                    label: 'Urgency Language',
                    value: criticalFlags.length > 0 ? 'Triggered by strong scam phrases' : 'None detected',
                },
                {
                    label: 'Scam Phrase Matches',
                    value: `${scamPhrases.length} of ${SCAM_PHRASE_LIBRARY_SIZE} checked`,
                },
            ];

            const salarySignal = salaryRisk >= 50
                ? 'High-risk salary language'
                : (salaryRisk >= 25 ? 'Review salary claims' : 'No unrealistic salary signal');
            const salaryAssessment = salaryRisk >= 50
                ? 'Potentially unrealistic'
                : (salaryRisk >= 25 ? 'Needs manual review' : 'Normal range');

            const salaryCards = [
                {
                    label: 'Salary Risk Score',
                    value: formatPercent(salaryRisk),
                    caption: 'rule-based signal',
                    tone: toneForScore(salaryRisk),
                },
                {
                    label: 'Phrase Trigger',
                    value: salaryRisk >= 50 && scamPhrases.length
                        ? scamPhrases.find((item) => ['guaranteed salary', 'earn per day', 'weekly salary', 'work from home earn'].includes(String(item).toLowerCase())) || 'Flagged'
                        : 'None detected',
                    caption: 'pay-related pattern',
                    tone: salaryRisk >= 50 ? 'danger' : 'safe',
                },
                {
                    label: 'Assessment',
                    value: salaryAssessment,
                    caption: 'analyst reading',
                    tone: toneForScore(salaryRisk),
                },
            ];

            let narrative = createSafeNarrative({ recommendedAction: recommendation });
            if (verdictKey === 'caution') narrative = createCautionNarrative({ recommendedAction: recommendation });
            if (verdictKey === 'fraud') narrative = createFraudNarrative({ recommendedAction: recommendation });
            if (verdictKey === 'error') narrative = createErrorNarrative(recommendation);

            const patternMessage = scamPhrases.length === 0
                ? `No matches found in the analyzer's built-in scam phrase library. This posting does not resemble the currently flagged phrase set.`
                : `The posting matched ${scamPhrases.length} built-in scam phrase${scamPhrases.length > 1 ? 's' : ''}: ${scamPhrases.slice(0, 4).join(', ')}.`;
            const patternTone = verdictKey === 'fraud' || scamPhrases.length > 0 ? (verdictKey === 'fraud' ? 'danger' : 'warn') : 'safe';

            const criticalNote = criticalFlags.length > 0
                ? `Critical phrase matches detected: ${criticalFlags.join(', ')}.`
                : (warningSignals.length
                    ? 'No critical red flags were matched, but the minor warnings above should still be reviewed manually.'
                    : 'No critical red flags found in the current analyzer output.');

            return {
                sourceUrl,
                hostname,
                score,
                verdictKey,
                theme,
                confidencePct,
                recommendedAction: recommendation || 'Analysis complete.',
                narrative,
                authenticSignals: authenticSignals.slice(0, 6),
                warningSignals: warningSignals.slice(0, 5),
                criticalNote,
                subscoreCards,
                urlIntelCards,
                verificationRows,
                contentChips,
                contentRows,
                salaryCards,
                salaryRisk,
                salarySummary: `Salary realism check result: ${salarySignal}. Current salary risk is ${salaryRisk}%, which the analyzer interprets as ${salaryAssessment.toLowerCase()}.`,
                patternMessage,
                patternTone,
                patternMeta: `Checked ${SCAM_PHRASE_LIBRARY_SIZE} built-in scam phrases on ${new Date().toLocaleString()} | Source: local analyzer rules`,
                summaryBadge: theme.summary,
            };
        }

        function renderRing(elementId, percent, color) {
            const element = document.getElementById(elementId);
            if (!element) return;
            element.style.setProperty('--ring-progress', clamp(percent));
            element.style.setProperty('--ring-color', color);
        }

        function renderSubscores(model) {
            const container = document.getElementById('subscoreGrid');
            if (!container) return;

            container.innerHTML = model.subscoreCards.map((item) => {
                const tone = toneForScore(item.value);
                const fillClass = fillClassForTone(tone);
                const textClass = toneClassForText(tone);
                return `
                    <div class="subscore-card">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="text-[1.02rem] text-slate-100 leading-snug">${escapeHtml(item.label)}</div>
                            <div class="text-xl font-bold font-['Outfit'] ${textClass}">${escapeHtml(formatPercent(item.value))}</div>
                        </div>
                        <div class="subscore-track mb-3">
                            <div class="subscore-fill ${fillClass}" style="width:${clamp(item.value)}%;"></div>
                        </div>
                        <p class="text-sm text-slate-400 leading-relaxed">${escapeHtml(item.note)}</p>
                    </div>
                `;
            }).join('');
        }

        function renderUrlIntel(model) {
            const container = document.getElementById('urlIntelGrid');
            if (!container) return;

            container.innerHTML = model.urlIntelCards.map((item) => {
                const toneClass = toneClassForText(item.tone);
                return `
                    <div class="intel-card">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-9 h-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-cyan-300">
                                <i class="fas ${escapeHtml(item.icon)}"></i>
                            </div>
                            <div class="text-sm text-slate-400">${escapeHtml(item.label)}</div>
                        </div>
                        <div class="text-[1.1rem] font-semibold ${toneClass} leading-snug">${escapeHtml(item.value)}</div>
                    </div>
                `;
            }).join('');
        }

        function renderVerification(model) {
            const container = document.getElementById('verificationList');
            if (!container) return;

            container.innerHTML = model.verificationRows.map((item) => {
                const toneText = toneClassForText(item.tone);
                const dotClass = dotToneClass(item.tone);
                return `
                    <div class="verification-row">
                        <div class="verification-row-label">
                            <span class="verification-dot ${dotClass}"></span>
                            <span>${escapeHtml(item.label)}</span>
                        </div>
                        <div class="verification-value ${toneText}">${escapeHtml(item.value)}</div>
                    </div>
                `;
            }).join('');
        }

        function renderSignalList(containerId, items, warning = false) {
            const container = document.getElementById(containerId);
            if (!container) return;

            container.innerHTML = items.length
                ? items.map((item) => `
                    <li>
                        <span class="signal-dot"></span>
                        <span>${escapeHtml(item)}</span>
                    </li>
                `).join('')
                : `<li><span class="signal-dot"></span><span>${warning ? 'No warnings were produced by the current scan.' : 'No authenticity signals were produced by the current scan.'}</span></li>`;
        }

        function renderSalary(model) {
            const grid = document.getElementById('salaryMetricGrid');
            if (grid) {
                grid.innerHTML = model.salaryCards.map((item) => {
                    const toneText = toneClassForText(item.tone);
                    return `
                        <div class="salary-card">
                            <div class="text-sm text-slate-400 mb-3">${escapeHtml(item.label)}</div>
                            <div class="text-[1.9rem] font-black font-['Outfit'] ${toneText} leading-none">${escapeHtml(item.value)}</div>
                            <div class="text-sm text-slate-500 mt-3">${escapeHtml(item.caption)}</div>
                        </div>
                    `;
                }).join('');
            }

            const meterFill = document.getElementById('salaryMeterFill');
            const meterMarker = document.getElementById('salaryMeterMarker');
            const summary = document.getElementById('salarySummary');
            if (meterFill) {
                meterFill.style.width = `${model.salaryRisk}%`;
                meterFill.className = `risk-meter-fill ${fillClassForTone(toneForScore(model.salaryRisk))}`;
            }
            if (meterMarker) meterMarker.style.left = `${model.salaryRisk}%`;
            if (summary) summary.textContent = model.salarySummary;
        }

        function renderContent(model) {
            const chipGroup = document.getElementById('contentChipGroup');
            const table = document.getElementById('contentTable');

            if (chipGroup) {
                chipGroup.innerHTML = model.contentChips.map((item) => `
                    <div class="content-chip ${escapeHtml(item.tone)}">
                        <i class="fas ${escapeHtml(item.icon)} text-xs"></i>
                        <span>${escapeHtml(item.label)}</span>
                    </div>
                `).join('');
            }

            if (table) {
                table.innerHTML = model.contentRows.map((item) => `
                    <div class="detail-table-row">
                        <div class="detail-table-label">${escapeHtml(item.label)}</div>
                        <div class="detail-table-value">${escapeHtml(item.value)}</div>
                    </div>
                `).join('');
            }
        }

        function renderPatternMatch(model) {
            const banner = document.getElementById('patternMatchBanner');
            const meta = document.getElementById('patternMatchMeta');
            if (!banner || !meta) return;

            const toneMap = {
                safe: {
                    wrapper: 'pattern-banner tone-safe',
                    icon: 'pattern-banner-icon tone-safe',
                    iconClass: 'fa-check',
                },
                warn: {
                    wrapper: 'pattern-banner tone-warn',
                    icon: 'pattern-banner-icon tone-warn',
                    iconClass: 'fa-triangle-exclamation',
                },
                danger: {
                    wrapper: 'pattern-banner tone-danger',
                    icon: 'pattern-banner-icon tone-danger',
                    iconClass: 'fa-ban',
                },
            };

            const tone = toneMap[model.patternTone] || toneMap.safe;
            banner.className = tone.wrapper;
            banner.innerHTML = `
                <div class="${tone.icon}">
                    <i class="fas ${tone.iconClass}"></i>
                </div>
                <p class="text-base md:text-lg leading-relaxed text-white">${escapeHtml(model.patternMessage)}</p>
            `;
            meta.textContent = model.patternMeta;
        }

        function renderDashboard(rawData, requestedUrl = '') {
            const model = normalizeAnalysis(rawData, requestedUrl);
            const theme = model.theme;

            const results = document.getElementById('analysisResults');
            if (results) results.classList.remove('hidden');

            renderRing('riskRing', model.score, theme.ringColor);
            renderRing('confidenceRing', model.confidencePct, theme.ringColor);

            document.getElementById('riskPercent').textContent = formatPercent(model.score);
            document.getElementById('riskStatus').textContent = theme.badge;
            document.getElementById('riskStatus').className = `status-pill ${theme.tone}`;

            document.getElementById('summaryBadge').textContent = model.summaryBadge;
            document.getElementById('summaryBadge').className = `mini-badge tone-${theme.tone === 'caution' ? 'warn' : (theme.tone === 'fraud' || theme.tone === 'error' ? 'danger' : 'safe')}`;
            document.getElementById('aiMessage').textContent = model.narrative;

            const actionBox = document.getElementById('actionBox');
            if (actionBox) {
                actionBox.className = `callout-shell tone-${theme.tone === 'caution' ? 'warn' : (theme.tone === 'fraud' || theme.tone === 'error' ? 'danger' : 'safe')}`;
                actionBox.innerHTML = `
                    <div class="flex items-start gap-3">
                        <div class="callout-icon tone-${theme.tone === 'caution' ? 'warn' : (theme.tone === 'fraud' || theme.tone === 'error' ? 'danger' : 'safe')}">
                            <i class="fas ${theme.actionIcon}"></i>
                        </div>
                        <div>
                            <p class="hud-title text-[0.72rem] mb-1">${theme.tone === 'error' ? 'Scan Status' : 'Recommended Action'}</p>
                            <p class="text-base font-semibold text-white leading-relaxed">${escapeHtml(model.recommendedAction)}</p>
                        </div>
                    </div>
                `;
            }

            document.getElementById('confidencePercent').textContent = formatPercent(model.confidencePct);
            document.getElementById('confidenceLabel').textContent = model.confidencePct >= 75 ? 'HIGH' : (model.confidencePct >= 45 ? 'MEDIUM' : 'LOW');
            document.getElementById('confidenceLabel').className = `text-[0.78rem] mt-4 uppercase tracking-[0.22em] font-semibold ${toneClassForText(theme.tone === 'caution' ? 'warn' : (theme.tone === 'fraud' || theme.tone === 'error' ? 'danger' : 'safe'))}`;

            const glow = document.getElementById('threatGlow');
            if (glow) glow.className = `absolute inset-0 opacity-15 blur-3xl transition-colors duration-700 ${theme.glow}`;

            renderSubscores(model);
            renderUrlIntel(model);
            renderVerification(model);
            renderSignalList('authenticSignalsList', model.authenticSignals, false);
            renderSignalList('warningSignalsList', model.warningSignals, true);
            document.getElementById('warningNote').textContent = model.criticalNote;
            renderSalary(model);
            renderContent(model);
            renderPatternMatch(model);

            return model;
        }

        function setLoadingState(isLoading) {
            const btn = document.getElementById('analyzeBtn');
            const results = document.getElementById('analysisResults');
            if (!btn) return;

            if (isLoading) {
                btn.disabled = true;
                btn.innerHTML = `<span class="animate-spin text-lg"><i class="fas fa-circle-notch"></i></span><span>Processing...</span>`;
                if (results) results.classList.add('hidden');
            } else {
                btn.disabled = false;
                btn.innerHTML = `<span>Analyze Target</span><i class="fas fa-radar"></i>`;
            }
        }

        function setErrorState(message, url = '') {
            renderDashboard({
                status: 'Error',
                score: 0,
                confidence_pct: 0,
                recommendation: message,
                explain: message,
                message: message,
                warning_signals: ['Target blocked the scanner or could not be parsed'],
                authentic_signals: [],
                scam_phrases_matched: [],
                url: url,
            }, url);
        }

        function createHistoryCard(item) {
            const card = document.createElement('div');
            card.className = 'p-3 rounded-xl border border-white/5 bg-white/[0.03] hover:bg-white/[0.06] transition-colors mb-2 cursor-pointer flex flex-col';

            const safeUrl = String(item.url || '');
            const safeStatus = String(item.status || 'SAFE');
            const safeDate = String(item.date || '');
            const safeTone = String(item.tone || '').toLowerCase();

            card.addEventListener('click', () => {
                const input = document.getElementById('jobUrlInput');
                if (input) input.value = safeUrl;
            });

            const urlNode = document.createElement('div');
            urlNode.className = 'text-xs text-slate-300 truncate w-full opacity-80 mb-3 font-mono';
            urlNode.textContent = safeUrl;

            const row = document.createElement('div');
            row.className = 'flex justify-between items-center w-full gap-3';

            const badge = document.createElement('span');
            let badgeClass = 'text-cyan-300 bg-cyan-500/10 border-cyan-500/20';
            if (safeTone === 'fraud') badgeClass = 'text-rose-300 bg-rose-500/10 border-rose-500/20';
            if (safeTone === 'caution') badgeClass = 'text-amber-300 bg-amber-500/10 border-amber-500/20';
            if (safeTone === 'error') badgeClass = 'text-rose-300 bg-rose-500/10 border-rose-500/20';
            badge.className = `text-xs font-bold px-2.5 py-1 rounded border ${badgeClass} uppercase tracking-wider`;
            badge.textContent = safeStatus;

            const dateNode = document.createElement('span');
            dateNode.className = 'text-xs text-slate-500 whitespace-nowrap';
            dateNode.textContent = safeDate;

            row.appendChild(badge);
            row.appendChild(dateNode);
            card.appendChild(urlNode);
            card.appendChild(row);
            return card;
        }

        function renderHistory() {
            const history = JSON.parse(localStorage.getItem('scanHistory') || '[]');
            const container = document.getElementById('historyBody');
            if (!container) return;

            container.replaceChildren();
            history.forEach((item) => {
                container.appendChild(createHistoryCard(item));
            });
        }

        function openSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (!sidebar || !overlay) return;
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (!sidebar || !overlay) return;
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        async function analyzeUrl() {
            const input = document.getElementById('jobUrlInput');
            const url = String(input?.value || '').trim();
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            if (!url) {
                alert('Please enter a target URL.');
                return;
            }

            setLoadingState(true);

            try {
                const response = await fetch('/scan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ url }),
                });

                let data = {};
                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    data = await response.json();
                }

                if (!response.ok) {
                    throw new Error(data.error || data.message || `Scan request failed (${response.status}).`);
                }

                if (data.status === 'Error' || data.error) {
                    setErrorState(data.message || data.error || 'Could not read text from this link. Anti-bot protection may have blocked the scanner.', url);
                    return;
                }

                const model = renderDashboard(data, url);
                const historyItem = {
                    url,
                    status: model.theme.badge,
                    tone: model.verdictKey,
                    date: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                };

                const history = JSON.parse(localStorage.getItem('scanHistory') || '[]');
                history.unshift(historyItem);
                localStorage.setItem('scanHistory', JSON.stringify(history.slice(0, 8)));
                renderHistory();
            } catch (error) {
                setErrorState(error?.message || 'Network exception: Is the Python security service running?', url);
                console.error(error);
            } finally {
                setLoadingState(false);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            renderHistory();

            const openBtn = document.getElementById('openSidebarBtn');
            const overlay = document.getElementById('sidebarOverlay');
            const input = document.getElementById('jobUrlInput');

            if (openBtn) openBtn.addEventListener('click', openSidebar);
            if (overlay) overlay.addEventListener('click', closeSidebar);

            if (input) {
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        analyzeUrl();
                    }
                });
            }

            window.addEventListener('resize', function () {
                if (window.innerWidth >= 768) {
                    document.body.classList.remove('overflow-hidden');
                    const overlayNode = document.getElementById('sidebarOverlay');
                    if (overlayNode) overlayNode.classList.add('hidden');
                }
            });
        });
    
