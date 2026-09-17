(function () {
    'use strict';

    const RATES_KEY = 'surf4miles.rates';
    const COMPARISON_KEY = 'surf4miles.comparison';
    const ACTIVE_PROFILE_KEY = 'surf4miles.activeProfile';
    const PROFILE_NAMES_KEY = 'surf4miles.profileNames';
    const DEFAULT_PROFILE = 'My Surf';
    const DEFAULT_COMPARISON_MPG = 40;
    const REPORT_ENDPOINT = 'api/report.php';

    const els = {
        profileSelect: document.getElementById('profile-select'),
        newProfileBtn: document.getElementById('new-profile-btn'),
        deleteProfileBtn: document.getElementById('delete-profile-btn'),
        standardRate: document.getElementById('standard-rate'),
        peakSaveRate: document.getElementById('peak-save-rate'),
        comparisonFuelType: document.getElementById('comparison-fuel-type'),
        comparisonMpg: document.getElementById('comparison-mpg'),
        ecInput: document.getElementById('ec-database-input'),
        addTripsBtn: document.getElementById('add-trips-btn'),
        downloadBtn: document.getElementById('download-btn'),
        statusMessage: document.getElementById('status-message'),
        reportContainer: document.getElementById('report-container'),
        generatedAt: document.getElementById('generated-at'),
    };

    function getProfileNames() {
        try {
            const raw = JSON.parse(localStorage.getItem(PROFILE_NAMES_KEY) || 'null');
            if (Array.isArray(raw) && raw.length > 0) return raw;
        } catch (e) { /* fall through to default */ }
        return [DEFAULT_PROFILE];
    }

    function saveProfileNames(names) {
        localStorage.setItem(PROFILE_NAMES_KEY, JSON.stringify(names));
    }

    function getActiveProfile() {
        return localStorage.getItem(ACTIVE_PROFILE_KEY) || getProfileNames()[0];
    }

    function setActiveProfile(name) {
        localStorage.setItem(ACTIVE_PROFILE_KEY, name);
    }

    function loadRates() {
        try {
            const raw = JSON.parse(localStorage.getItem(RATES_KEY) || 'null');
            if (raw && typeof raw.standardRate === 'number' && typeof raw.peakSaveRate === 'number') {
                return raw;
            }
        } catch (e) { /* fall through to default */ }
        return { standardRate: 0, peakSaveRate: 0 };
    }

    function saveRates(rates) {
        localStorage.setItem(RATES_KEY, JSON.stringify(rates));
    }

    function currentRatesFromInputs() {
        return {
            standardRate: parseFloat(els.standardRate.value) || 0,
            peakSaveRate: parseFloat(els.peakSaveRate.value) || 0,
        };
    }

    function loadComparisonSettings() {
        try {
            const raw = JSON.parse(localStorage.getItem(COMPARISON_KEY) || 'null');
            if (raw && (raw.fuelType === 'petrol' || raw.fuelType === 'diesel') && typeof raw.mpg === 'number') {
                return raw;
            }
        } catch (e) { /* fall through to default */ }
        return { fuelType: 'petrol', mpg: DEFAULT_COMPARISON_MPG };
    }

    function saveComparisonSettings(settings) {
        localStorage.setItem(COMPARISON_KEY, JSON.stringify(settings));
    }

    function currentComparisonFromInputs() {
        return {
            fuelType: els.comparisonFuelType.value === 'diesel' ? 'diesel' : 'petrol',
            mpg: parseFloat(els.comparisonMpg.value) || DEFAULT_COMPARISON_MPG,
        };
    }

    function setStatus(message, isError) {
        els.statusMessage.textContent = message || '';
        els.statusMessage.classList.toggle('error', !!isError);
    }

    function renderEmptyState() {
        els.reportContainer.innerHTML =
            '<p class="empty-state">No data yet for this profile &mdash; upload your <code>EC_database.db</code> above to get started.</p>';
        els.generatedAt.textContent = '';
    }

    function renderReportHtml(html, generatedAtIso) {
        els.reportContainer.innerHTML = html;
        if (generatedAtIso) {
            const d = new Date(generatedAtIso);
            els.generatedAt.textContent = 'Report generated ' + d.toLocaleString();
        }
    }

    function refreshProfileSelect() {
        const names = getProfileNames();
        const active = getActiveProfile();
        els.profileSelect.innerHTML = '';
        for (const name of names) {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            if (name === active) opt.selected = true;
            els.profileSelect.appendChild(opt);
        }
    }

    function base64ToBlob(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return new Blob([bytes], { type: 'application/x-sqlite3' });
    }

    async function callReportApi({ ecFile, cumulativeBlob, rates, comparison }) {
        const form = new FormData();
        if (ecFile) form.append('ec_database', ecFile, 'EC_database.db');
        if (cumulativeBlob) form.append('cumulative_db', cumulativeBlob, 'cumulative.db');
        form.append('standard_rate', String(rates.standardRate));
        form.append('peak_save_rate', String(rates.peakSaveRate));
        form.append('comparison_fuel_type', comparison.fuelType);
        form.append('comparison_mpg', String(comparison.mpg));

        const response = await fetch(REPORT_ENDPOINT, { method: 'POST', body: form });
        const data = await response.json().catch(() => null);

        if (!response.ok || !data || !data.ok) {
            throw new Error((data && data.error) || 'Request failed (' + response.status + ')');
        }
        return data;
    }

    async function loadReportForActiveProfile() {
        const profile = await Surf4MilesStore.getProfile(getActiveProfile());
        if (!profile || !profile.dbBlob) {
            renderEmptyState();
            return;
        }

        setStatus('Loading report…', false);
        try {
            const data = await callReportApi({
                cumulativeBlob: profile.dbBlob,
                rates: currentRatesFromInputs(),
                comparison: currentComparisonFromInputs(),
            });
            renderReportHtml(data.report_html, data.generated_at);
            setStatus('', false);
        } catch (err) {
            setStatus('Could not load report: ' + err.message, true);
        }
    }

    async function handleAddTrips() {
        const file = els.ecInput.files[0];
        if (!file) {
            setStatus('Choose your EC_database.db file first', true);
            return;
        }

        els.addTripsBtn.disabled = true;
        setStatus('Uploading and merging trips…', false);

        try {
            const activeName = getActiveProfile();
            const existing = await Surf4MilesStore.getProfile(activeName);
            const data = await callReportApi({
                ecFile: file,
                cumulativeBlob: existing ? existing.dbBlob : null,
                rates: currentRatesFromInputs(),
                comparison: currentComparisonFromInputs(),
            });

            const blob = base64ToBlob(data.cumulative_db_base64);
            await Surf4MilesStore.putProfile(activeName, blob);

            renderReportHtml(data.report_html, data.generated_at);
            setStatus('Imported ' + data.trips_imported + ' new trip(s). Total trips: ' + data.total_trips + '.', false);
            els.ecInput.value = '';
        } catch (err) {
            setStatus('Could not add trips: ' + err.message, true);
        } finally {
            els.addTripsBtn.disabled = false;
        }
    }

    async function handleDownload() {
        const activeName = getActiveProfile();
        const profile = await Surf4MilesStore.getProfile(activeName);
        if (!profile || !profile.dbBlob) {
            setStatus('No data to download yet for this profile', true);
            return;
        }

        const safeName = activeName.replace(/[^A-Za-z0-9 _-]/g, '').trim() || 'surf4miles';
        const dateStamp = new Date().toISOString().slice(0, 10);
        const url = URL.createObjectURL(profile.dbBlob);
        const a = document.createElement('a');
        a.href = url;
        a.download = safeName + '-' + dateStamp + '.db';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    function handleNewProfile() {
        const name = (window.prompt('Name for this car / profile:', '') || '').trim().slice(0, 40);
        if (!name) return;

        const names = getProfileNames();
        if (!names.includes(name)) {
            names.push(name);
            saveProfileNames(names);
        }
        setActiveProfile(name);
        refreshProfileSelect();
        renderEmptyState();
        setStatus('Switched to new profile "' + name + '"', false);
    }

    async function handleDeleteProfile() {
        const activeName = getActiveProfile();
        if (!window.confirm('Delete all locally-stored data for "' + activeName + '"? This cannot be undone.')) {
            return;
        }

        await Surf4MilesStore.deleteProfile(activeName);

        let names = getProfileNames().filter((n) => n !== activeName);
        if (names.length === 0) names = [DEFAULT_PROFILE];
        saveProfileNames(names);
        setActiveProfile(names[0]);

        refreshProfileSelect();
        await loadReportForActiveProfile();
        setStatus('Deleted data for "' + activeName + '"', false);
    }

    function handleSettingsChange() {
        saveRates(currentRatesFromInputs());
        saveComparisonSettings(currentComparisonFromInputs());
        loadReportForActiveProfile();
    }

    async function init() {
        const rates = loadRates();
        els.standardRate.value = rates.standardRate || '';
        els.peakSaveRate.value = rates.peakSaveRate || '';

        const comparison = loadComparisonSettings();
        els.comparisonFuelType.value = comparison.fuelType;
        els.comparisonMpg.value = comparison.mpg;

        refreshProfileSelect();

        els.profileSelect.addEventListener('change', () => {
            setActiveProfile(els.profileSelect.value);
            loadReportForActiveProfile();
        });
        els.newProfileBtn.addEventListener('click', handleNewProfile);
        els.deleteProfileBtn.addEventListener('click', handleDeleteProfile);
        els.standardRate.addEventListener('change', handleSettingsChange);
        els.peakSaveRate.addEventListener('change', handleSettingsChange);
        els.comparisonFuelType.addEventListener('change', handleSettingsChange);
        els.comparisonMpg.addEventListener('change', handleSettingsChange);
        els.addTripsBtn.addEventListener('click', handleAddTrips);
        els.downloadBtn.addEventListener('click', handleDownload);

        await loadReportForActiveProfile();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
