(function () {
  "use strict";

  // Cache the in-flight fetch *promise*, not just the resolved data.
  // If two instances of the shortcode exist on the same page, both call
  // initRoot() before either has finished loading, so caching only the
  // resolved value let both kick off their own fetch() (a real, if minor,
  // race condition). Caching the promise means the second instance just
  // awaits the first instance's already-in-flight request.
  let scoringDataPromise = null;
  let scoringData = null;

  function loadScoringData() {
    if (!scoringDataPromise) {
      scoringDataPromise = fetch(GOC_PLUGIN.dataUrl).then(function (response) {
        if (!response.ok) throw new Error("Could not load Germany Job Offer Checker data.");
        return response.json();
      });
    }
    return scoringDataPromise;
  }

  function euro(value) {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "EUR",
      maximumFractionDigits: 0
    }).format(value || 0);
  }

  // Escapes any value before it is interpolated into an innerHTML template.
  // The values below (city/role names, the checklist URL) currently only
  // ever come from the plugin's own JSON file, so this isn't exploitable
  // today -- but it's cheap, defense-in-depth hardening against the data
  // file someday becoming admin-editable or sourced from user input.
  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function cssStatus(status) {
    const normalized = String(status || "").toLowerCase().replace(/\s+/g, "");
    if (normalized === "green") return "goc-green";
    if (normalized === "yellow") return "goc-yellow";
    if (normalized === "red") return "goc-red";
    return "goc-notchecked";
  }

  function scoreFromRatio(ratio) {
    if (ratio < 0.75) return 2;
    if (ratio < 0.90) return 4;
    if (ratio < 1.05) return 6;
    if (ratio < 1.25) return 8;
    return 10;
  }

  function getVerdict(score) {
    if (score < 4) return "Red";
    if (score <= 6.5) return "Yellow";
    return "Green";
  }

  function getMarketScore(salary, band) {
    if (salary < band.low * 0.85) return 2;
    if (salary < band.low) return 4;
    if (salary < band.mid) return 6;
    if (salary < band.strong) return 8;
    return 10;
  }

  function getExperienceScore(salary, band) {
    if (salary < band.low) return 3;
    if (salary < band.mid) return 5;
    if (salary < band.strong) return 8;
    return 10;
  }

  function getBlueCardStatus(input) {
    const year = scoringData.blueCard.year;

    if (input.bluecardRelevant === "no") {
      return {
        status: "Not checked",
        threshold: null,
        message: "Blue Card threshold was not checked because you marked it as not relevant."
      };
    }

    const roleData = scoringData.roles[input.role];
    const threshold = roleData && roleData.shortageOccupation
      ? scoringData.blueCard.shortage
      : scoringData.blueCard.standard;

    if (input.salary >= threshold) {
      return {
        status: "Green",
        threshold: threshold,
        message: "Your salary appears above the " + year + " threshold of " + euro(threshold) + "."
      };
    }

    if (input.salary >= threshold * 0.9) {
      return {
        status: "Yellow",
        threshold: threshold,
        message: "Your salary is close to the " + year + " threshold of " + euro(threshold) + ". Verify carefully before relying on this."
      };
    }

    return {
      status: "Red",
      threshold: threshold,
      message: "Your salary appears below the " + year + " threshold of " + euro(threshold) + ". This may be a serious issue if the Blue Card route is relevant."
    };
  }

  function calculateOffer(input) {
    const cityData = scoringData.cities[input.city] || scoringData.cities["Other Germany"];
    const roleData = scoringData.roles[input.role] || scoringData.roles["Other"];
    const band = roleData.bands[input.experience] || roleData.bands["3-5"];

    const comfortFloor = cityData[input.household] || cityData.single;
    const affordabilityScore = scoreFromRatio(input.salary / comfortFloor);
    const marketScore = getMarketScore(input.salary, band);
    const experienceScore = getExperienceScore(input.salary, band);

    const finalScore = Number((
      affordabilityScore * 0.35 +
      marketScore * 0.35 +
      experienceScore * 0.30
    ).toFixed(1));

    return {
      finalScore: finalScore,
      verdict: getVerdict(finalScore),
      affordabilityScore: affordabilityScore,
      marketScore: marketScore,
      experienceScore: experienceScore,
      blueCard: getBlueCardStatus(input),
      comfortFloor: comfortFloor,
      band: band,
      rentPressure: cityData.rentPressure || "Medium",
      cityTier: cityData.tier || "Medium"
    };
  }

  function renderResult(root, result, input) {
    const resultEl = root.querySelector("[data-goc-result]");
    const negotiation = result.marketScore <= 4
      ? "Negotiate strongly or investigate carefully."
      : result.marketScore <= 6
        ? "Consider negotiating."
        : "Offer appears reasonable; negotiate if you have leverage.";

    const safeCity = escapeHtml(input.city);
    const safeRole = escapeHtml(input.role);
    const safeExperience = escapeHtml(input.experience);
    const checklistUrl = escapeHtml(GOC_PLUGIN.checklistUrl || "/germany-job-offer-checklist/");

    resultEl.innerHTML = `
      <div class="goc-score-head">
        <div class="goc-score-label">Your Offer Reality Score</div>
        <div class="goc-score-line">
          <div class="goc-score-number">${result.finalScore.toFixed(1)}/10</div>
          <span class="goc-pill ${cssStatus(result.verdict)}">${result.verdict}</span>
        </div>
      </div>
      <div class="goc-panels">
        <div class="goc-panel">
          <div class="goc-mini-score">${result.affordabilityScore}/10</div>
          <h4>Can you live on this?</h4>
          <p>For ${safeCity}, the v0 comfort floor for this household type is around ${euro(result.comfortFloor)} gross/year. Rent pressure: ${result.rentPressure}.</p>
        </div>
        <div class="goc-panel">
          <div class="goc-mini-score">${result.marketScore}/10</div>
          <h4>Market fairness check</h4>
          <p>For ${safeRole} with ${safeExperience} experience, the rough v0 band is ${euro(result.band.low)}-${euro(result.band.strong)}. A low score here may indicate lowball risk.</p>
        </div>
        <div class="goc-panel">
          <div class="goc-mini-score">${result.experienceScore}/10</div>
          <h4>Experience fit</h4>
          <p>This checks whether the offer aligns with the expected range for your declared experience band.</p>
        </div>
        <div class="goc-panel">
          <div class="goc-mini-score"><span class="goc-pill ${cssStatus(result.blueCard.status)}">${result.blueCard.status}</span></div>
          <h4>Blue Card check</h4>
          <p>${result.blueCard.message}</p>
        </div>
      </div>
      <div class="goc-explain">
        <h4>Negotiation signal</h4>
        <p>${negotiation}</p>
        <div class="goc-callout">
          <strong>Next step:</strong> Use a German brutto-netto calculator for exact take-home pay and verify Blue Card thresholds with official German sources.
        </div>
        <p class="goc-disclaimer">This is an educational estimate based on public and manually curated data. It is not legal, immigration, tax, financial, or career advice. Salary bands vary by company, role, city, household situation, and individual circumstances.</p>
        <a class="goc-cta-link" href="${checklistUrl}">Download the Germany Job Offer Checklist</a>
      </div>
    `;
  }

  function populateSelect(select, values) {
    select.innerHTML = "";
    values.forEach(function (value) {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = value;
      select.appendChild(option);
    });
  }

  // Field lookups are scoped to data-goc-field attributes rather than
  // hardcoded #id selectors. The PHP side now gives every shortcode
  // instance a unique id (for accessible <label for> association), so the
  // JS can no longer rely on a single fixed id string -- data attributes
  // stay constant across instances and are still scoped to `root`.
  function field(root, name) {
    return root.querySelector('[data-goc-field="' + name + '"]');
  }

  async function initRoot(root) {
    scoringData = await loadScoringData();

    populateSelect(field(root, "city"), Object.keys(scoringData.cities));
    populateSelect(field(root, "role"), Object.keys(scoringData.roles));

    const salaryInput = field(root, "salary");
    const citySelect = field(root, "city");
    const roleSelect = field(root, "role");
    const experienceSelect = field(root, "experience");
    const householdSelect = field(root, "household");
    const bluecardSelect = field(root, "bluecardRelevant");

    // Neutral default scenario for first-time users. Result still appears only after clicking the button.
    salaryInput.value = "60000";
    if (scoringData.cities["Berlin"]) citySelect.value = "Berlin";
    if (scoringData.roles["Software / IT"]) roleSelect.value = "Software / IT";
    experienceSelect.value = "3-5";
    householdSelect.value = "single";
    bluecardSelect.value = "yes";

    const form = root.querySelector("[data-goc-form]");
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      const input = {
        salary: Number(field(root, "salary").value),
        city: field(root, "city").value,
        role: field(root, "role").value,
        experience: field(root, "experience").value,
        household: field(root, "household").value,
        bluecardRelevant: field(root, "bluecardRelevant").value
      };
      const result = calculateOffer(input);
      renderResult(root, result, input);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".goc").forEach(function (root) {
      initRoot(root).catch(function (error) {
        const result = root.querySelector("[data-goc-result]");
        if (result) {
          result.innerHTML = '<div class="goc-placeholder"><h3>Could not load calculator</h3><p>' + escapeHtml(error.message) + '</p></div>';
        }
      });
    });
  });
})();
