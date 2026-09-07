<?php
// bmi_calculator.php
require 'config.php';
require 'auth.php';
require_login();
require_role('member');

date_default_timezone_set('Asia/Kolkata');
$user_id = (int)($_SESSION['user_id'] ?? 0);

function html($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* ── Create bmi_records table if not exists ── */
$conn->query("CREATE TABLE IF NOT EXISTS bmi_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    height_cm DECIMAL(5,2) NOT NULL,
    weight_kg DECIMAL(5,2) NOT NULL,
    bmi DECIMAL(5,2) NOT NULL,
    category VARCHAR(30) NOT NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bmi_user (user_id)
)");

$saved_bmi    = null;
$saved_cat    = null;
$saved_height = null;
$saved_weight = null;
$errors       = [];

/* ── Handle form submission ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate'])) {
    $height = (float)($_POST['height'] ?? 0);
    $weight = (float)($_POST['weight'] ?? 0);

    if ($height <= 0 || $height > 300)  $errors[] = 'Please enter a valid height (1–300 cm).';
    if ($weight <= 0 || $weight > 500)  $errors[] = 'Please enter a valid weight (1–500 kg).';

    if (empty($errors)) {
        $h_m  = $height / 100;
        $bmi  = round($weight / ($h_m * $h_m), 2);

        if ($bmi < 18.5)      $category = 'Underweight';
        elseif ($bmi < 25)   $category = 'Normal Weight';
        elseif ($bmi < 30)   $category = 'Overweight';
        else                  $category = 'Obese';

        $st = $conn->prepare("INSERT INTO bmi_records (user_id, height_cm, weight_kg, bmi, category) VALUES (?,?,?,?,?)");
        if ($st) {
            $st->bind_param('iddds', $user_id, $height, $weight, $bmi, $category);
            $st->execute();
            $st->close();
        }
        // Redirect + pass values via session so PRG pattern works
        $_SESSION['bmi_result'] = ['bmi'=>$bmi,'category'=>$category,'height'=>$height,'weight'=>$weight];
        header('Location: bmi_calculator.php?saved=1');
        exit;
    }
}

/* ── Retrieve flash result ── */
if (isset($_GET['saved']) && isset($_SESSION['bmi_result'])) {
    $r            = $_SESSION['bmi_result'];
    $saved_bmi    = $r['bmi'];
    $saved_cat    = $r['category'];
    $saved_height = $r['height'];
    $saved_weight = $r['weight'];
    unset($_SESSION['bmi_result']);
}

/* ── BMI History ── */
$history = [];
$hst = $conn->prepare("SELECT * FROM bmi_records WHERE user_id=? ORDER BY calculated_at DESC LIMIT 50");
if ($hst) {
    $hst->bind_param('i', $user_id);
    $hst->execute();
    $hres = $hst->get_result();
    while ($row = $hres->fetch_assoc()) $history[] = $row;
    $hst->close();
}

/* ── Diet & Supplement suggestions ── */
$suggestions = [
    'Underweight' => [
        'icon'  => '🍗',
        'color' => '#3b82f6',
        'title' => 'Underweight — Gain Healthy Mass',
        'diet'  => [
            'Eat calorie-dense foods: nuts, avocado, whole milk, eggs',
            'Aim for 5–6 small meals per day',
            'Include complex carbs: oats, brown rice, sweet potatoes',
            'High-protein meals: chicken, fish, legumes, dairy',
            'Add healthy fats: olive oil, nut butters, seeds',
        ],
        'supplements' => [
            ['name'=>'Mass Gainer',   'desc'=>'High-calorie shake to increase daily intake'],
            ['name'=>'Whey Protein',  'desc'=>'Supports muscle growth and repair'],
            ['name'=>'Omega-3',       'desc'=>'Healthy fats for overall health'],
            ['name'=>'Multivitamins','desc'=>'Fill nutritional gaps'],
            ['name'=>'Creatine',      'desc'=>'Increases strength and muscle mass'],
        ],
    ],
    'Normal Weight' => [
        'icon'  => '🥗',
        'color' => '#22c55e',
        'title' => 'Normal Weight — Maintain & Optimize',
        'diet'  => [
            'Follow a balanced diet with all macronutrients',
            'Eat plenty of vegetables, fruits, and whole grains',
            'Lean proteins: chicken, fish, tofu, eggs',
            'Stay hydrated: 2–3 litres of water daily',
            'Limit processed foods, sugar, and trans fats',
        ],
        'supplements' => [
            ['name'=>'Whey Protein',  'desc'=>'Support muscle maintenance and recovery'],
            ['name'=>'Multivitamins','desc'=>'Ensure micronutrient sufficiency'],
            ['name'=>'Omega-3',       'desc'=>'Heart health and anti-inflammation'],
            ['name'=>'Vitamin D',     'desc'=>'Bone health and immunity'],
        ],
    ],
    'Overweight' => [
        'icon'  => '🥦',
        'color' => '#f59e0b',
        'title' => 'Overweight — Smart Calorie Deficit',
        'diet'  => [
            'Create a moderate calorie deficit (300–500 kcal/day)',
            'High-protein, lower-carb diet to preserve muscle',
            'Fill half your plate with non-starchy vegetables',
            'Avoid sugary drinks, alcohol, and refined carbs',
            'Eat slowly and practice mindful eating',
        ],
        'supplements' => [
            ['name'=>'Whey Protein',      'desc'=>'Preserve lean muscle during weight loss'],
            ['name'=>'Green Tea Extract', 'desc'=>'Mild metabolism booster'],
            ['name'=>'L-Carnitine',       'desc'=>'Helps transport fat to be burned for energy'],
            ['name'=>'Fiber Supplement',  'desc'=>'Increases satiety and aids digestion'],
        ],
    ],
    'Obese' => [
        'icon'  => '🫑',
        'color' => '#ef4444',
        'title' => 'Obese — Structured Weight Management',
        'diet'  => [
            'Consult a nutritionist or doctor for a personalised plan',
            'Low-calorie, high-fibre diet (vegetables, legumes, oats)',
            'Strict avoidance of fried foods, sweets, and fast food',
            'Small frequent meals to manage hunger',
            'Drink water before meals to reduce overall intake',
        ],
        'supplements' => [
            ['name'=>'CLA (Conjugated Linoleic Acid)', 'desc'=>'Supports fat loss and lean mass'],
            ['name'=>'L-Carnitine',                    'desc'=>'Boosts fat metabolism'],
            ['name'=>'Fiber Supplement',               'desc'=>'Controls hunger and blood sugar'],
            ['name'=>'Multivitamins',                  'desc'=>'Prevent deficiencies during calorie restriction'],
        ],
    ],
];
$cat_colors = [
    'Underweight'   => ['badge'=>'badge-blue', 'bar'=>'#3b82f6', 'pct'=>15],
    'Normal Weight' => ['badge'=>'badge-green','bar'=>'#22c55e', 'pct'=>45],
    'Overweight'    => ['badge'=>'badge-yellow','bar'=>'#f59e0b','pct'=>70],
    'Obese'         => ['badge'=>'badge-red',  'bar'=>'#ef4444', 'pct'=>90],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>BMI Calculator — GymPro</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=1.2">
  <style>
    /* ── BMI page specific ── */
    .bmi-grid { display:grid; grid-template-columns:400px 1fr; gap:24px; align-items:start; }
    @media(max-width:900px){ .bmi-grid { grid-template-columns:1fr; } }

    /* Calculator card */
    .bmi-form-card { background:var(--card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; }
    .bmi-input-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    /* Gauge */
    .bmi-gauge-wrap { position:relative; margin:20px 0 10px; }
    .bmi-gauge-track { width:100%; height:12px; background:var(--bg-3); border-radius:999px; overflow:hidden; }
    .bmi-gauge-fill  { height:100%; border-radius:999px; transition:width 1s cubic-bezier(.4,0,.2,1); width:0; }
    .bmi-gauge-zones { display:flex; justify-content:space-between; font-size:10px; color:var(--text-3); margin-top:5px; }
    .bmi-result-num  { font-family:'Barlow Condensed',sans-serif; font-size:4rem; font-weight:800; line-height:1; }

    /* Result card */
    .result-panel { display:none; }
    .result-panel.show { display:block; animation:fadeUp .4s ease; }
    @keyframes fadeUp { from{ opacity:0; transform:translateY(16px); } to{ opacity:1; transform:translateY(0); } }

    /* Suggestions */
    .sug-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:14px; }
    @media(max-width:640px){ .sug-grid { grid-template-columns:1fr; } }
    .sug-box { background:var(--bg-3); border:1px solid var(--border); border-radius:var(--radius); padding:16px; }
    .sug-box h5 { margin:0 0 10px; font-size:13px; color:var(--text-2); display:flex; align-items:center; gap:6px; }
    .sug-box ul { padding-left:18px; margin:0; }
    .sug-box ul li { font-size:13px; color:var(--text-2); line-height:1.7; }
    .supp-chip { display:inline-flex; flex-direction:column; background:var(--bg-2); border:1px solid var(--border); border-radius:var(--radius); padding:8px 12px; margin:4px; font-size:12px; }
    .supp-chip strong { color:var(--text-1); font-size:13px; }
    .supp-chip span { color:var(--text-3); font-size:11px; margin-top:2px; }

    /* History table */
    .history-section { margin-top:28px; }
    .cat-bar { display:inline-block; width:6px; height:6px; border-radius:50%; margin-right:5px; vertical-align:middle; }

    /* Alert */
    .calc-alert { background:rgba(255,68,68,.1); border:1px solid rgba(255,68,68,.25); color:#f87171; border-radius:var(--radius); padding:12px 16px; margin-bottom:16px; font-size:14px; }
  </style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand">🏋️ GYM<span>PRO</span></div>
  <div class="navbar-links">
    <a href="member_dashboard.php">← Dashboard</a>
    <button class="theme-btn" id="themeBtn" title="Toggle theme" aria-label="Toggle theme">
      <span class="moon">🌙</span>
      <span class="sun">☀️</span>
    </button>
    <a href="logout.php" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="container">
  <div class="page-header">
    <div>
      <p style="margin:0;font-size:13px;color:var(--text-3);"><?php echo date('l, d F Y'); ?></p>
      <h2>BMI <span class="accent">Calculator</span></h2>
    </div>
  </div>

  <div class="bmi-grid">
    <!-- LEFT: Form -->
    <div>
      <div class="bmi-form-card">
        <div class="section-label">Calculate Your BMI</div>

        <?php if (!empty($errors)): ?>
          <div class="calc-alert"><?php echo implode('<br>', array_map('html',$errors)); ?></div>
        <?php endif; ?>

        <form method="POST" action="bmi_calculator.php" id="bmiForm">
          <div class="bmi-input-row">
            <div class="form-group">
              <label for="height">Height (cm)</label>
              <input type="number" id="height" name="height" placeholder="e.g. 175" min="50" max="300" step="0.1" required>
            </div>
            <div class="form-group">
              <label for="weight">Weight (kg)</label>
              <input type="number" id="weight" name="weight" placeholder="e.g. 70" min="10" max="500" step="0.1" required>
            </div>
          </div>

          <!-- Live Preview -->
          <div id="livePreview" style="display:none;margin-bottom:18px;">
            <div style="display:flex;align-items:baseline;gap:12px;margin-bottom:8px;">
              <div class="bmi-result-num" id="liveBmiNum" style="color:var(--accent);">—</div>
              <span id="liveCatBadge" class="badge">—</span>
            </div>
            <div class="bmi-gauge-wrap">
              <div class="bmi-gauge-track">
                <div class="bmi-gauge-fill" id="liveGaugeFill" style="background:var(--accent);"></div>
              </div>
              <div class="bmi-gauge-zones">
                <span>Under&shy;weight<br>&lt;18.5</span>
                <span>Normal<br>18.5–24.9</span>
                <span>Over&shy;weight<br>25–29.9</span>
                <span>Obese<br>≥30</span>
              </div>
            </div>
          </div>

          <button type="submit" name="calculate" class="btn" style="width:100%;justify-content:center;">⚖️ Calculate &amp; Save</button>
        </form>

        <p style="font-size:12px;color:var(--text-3);margin-top:14px;text-align:center;">
          BMI = Weight (kg) ÷ Height² (m) &nbsp;|&nbsp; Results are saved to your history automatically.
        </p>
      </div>

      <!-- BMI Reference Card -->
      <div class="card" style="margin-top:18px;">
        <div class="section-label">BMI Reference Chart</div>
        <table style="font-size:13px;width:100%;border-collapse:collapse;">
          <thead><tr>
            <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-3);">Category</th>
            <th style="padding:8px 10px;text-align:left;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-3);">BMI Range</th>
          </tr></thead>
          <tbody>
            <tr style="border-top:1px solid var(--border);"><td style="padding:10px;"><span class="badge badge-blue">Underweight</span></td><td style="padding:10px;color:var(--text-2);">&lt; 18.5</td></tr>
            <tr style="border-top:1px solid var(--border);"><td style="padding:10px;"><span class="badge badge-green">Normal Weight</span></td><td style="padding:10px;color:var(--text-2);">18.5 – 24.9</td></tr>
            <tr style="border-top:1px solid var(--border);"><td style="padding:10px;"><span class="badge badge-yellow">Overweight</span></td><td style="padding:10px;color:var(--text-2);">25.0 – 29.9</td></tr>
            <tr style="border-top:1px solid var(--border);"><td style="padding:10px;"><span class="badge badge-red">Obese</span></td><td style="padding:10px;color:var(--text-2);">≥ 30.0</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- RIGHT: Result + Suggestions + History -->
    <div>

      <!-- Saved Result Panel -->
      <?php if ($saved_bmi !== null):
        $sc   = $saved_cat;
        $meta = $cat_colors[$sc] ?? ['badge'=>'badge-gray','bar'=>'#aaa','pct'=>50];
        $sug  = $suggestions[$sc] ?? null;
      ?>
      <div class="card result-panel show" id="resultPanel">
        <div class="section-label">Your Result</div>
        <div style="display:flex;align-items:baseline;gap:16px;flex-wrap:wrap;margin-bottom:14px;">
          <div class="bmi-result-num" style="color:<?php echo $meta['bar']; ?>;"><?php echo html($saved_bmi); ?></div>
          <span class="badge <?php echo $meta['badge']; ?>"><?php echo html($sc); ?></span>
          <span style="font-size:13px;color:var(--text-3);"><?php echo html($saved_height); ?> cm · <?php echo html($saved_weight); ?> kg</span>
        </div>
        <div class="bmi-gauge-wrap">
          <div class="bmi-gauge-track">
            <div class="bmi-gauge-fill" id="savedGaugeFill" style="background:<?php echo $meta['bar']; ?>;width:0;"></div>
          </div>
          <div class="bmi-gauge-zones">
            <span>Under&shy;weight<br>&lt;18.5</span>
            <span>Normal<br>18.5–24.9</span>
            <span>Over&shy;weight<br>25–29.9</span>
            <span>Obese<br>≥30</span>
          </div>
        </div>

        <?php if ($sug): ?>
        <div style="margin-top:20px;">
          <div class="section-label"><?php echo $sug['icon']; ?> <?php echo html($sug['title']); ?></div>
          <div class="sug-grid">
            <div class="sug-box">
              <h5>🥗 Diet Recommendations</h5>
              <ul><?php foreach($sug['diet'] as $d): ?><li><?php echo html($d); ?></li><?php endforeach; ?></ul>
            </div>
            <div class="sug-box">
              <h5>💊 Supplement Suggestions</h5>
              <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;">
                <?php foreach($sug['supplements'] as $s): ?>
                  <div class="supp-chip">
                    <strong><?php echo html($s['name']); ?></strong>
                    <span><?php echo html($s['desc']); ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <?php else: ?>
      <!-- Placeholder before first calculation -->
      <div class="card" style="text-align:center;padding:48px 24px;" id="resultPlaceholder">
        <div style="font-size:56px;margin-bottom:16px;opacity:.3;">⚖️</div>
        <h4 style="color:var(--text-3);font-weight:500;">Enter your height &amp; weight to get your BMI result, personalised diet tips, and supplement recommendations.</h4>
      </div>
      <?php endif; ?>

      <!-- BMI History -->
      <div class="history-section">
        <div class="card">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <div class="section-label" style="margin-bottom:0;">📊 BMI History</div>
            <span style="font-size:12px;color:var(--text-3);"><?php echo count($history); ?> record(s)</span>
          </div>

          <?php if (!empty($history)): ?>
          <div class="table-wrap">
            <table>
              <thead><tr>
                <th>Date</th>
                <th>Height</th>
                <th>Weight</th>
                <th>BMI</th>
                <th>Category</th>
              </tr></thead>
              <tbody>
              <?php foreach($history as $h):
                $hc = $cat_colors[$h['category']] ?? ['badge'=>'badge-gray','bar'=>'#aaa'];
              ?>
                <tr>
                  <td style="font-size:12px;color:var(--text-3);white-space:nowrap;"><?php echo html($h['calculated_at']); ?></td>
                  <td style="font-size:13px;"><?php echo html($h['height_cm']); ?> cm</td>
                  <td style="font-size:13px;"><?php echo html($h['weight_kg']); ?> kg</td>
                  <td style="font-family:'Barlow Condensed',sans-serif;font-size:1.3rem;font-weight:700;color:<?php echo $hc['bar']; ?>;"><?php echo html($h['bmi']); ?></td>
                  <td><span class="badge <?php echo $hc['badge']; ?>"><?php echo html($h['category']); ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
            <p style="font-size:13px;color:var(--text-3);text-align:center;padding:24px 0;">No BMI records yet. Calculate your first BMI above!</p>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="assets/theme.js?v=1.2"></script>
<script>
/* ── Live preview while typing ── */
const heightInput = document.getElementById('height');
const weightInput = document.getElementById('weight');
const livePreview = document.getElementById('livePreview');
const liveBmiNum  = document.getElementById('liveBmiNum');
const liveCat     = document.getElementById('liveCatBadge');
const liveFill    = document.getElementById('liveGaugeFill');

const catConfig = {
  'Underweight':   { cls:'badge-blue',   color:'#3b82f6', pct:15 },
  'Normal Weight': { cls:'badge-green',  color:'#22c55e', pct:45 },
  'Overweight':    { cls:'badge-yellow', color:'#f59e0b', pct:70 },
  'Obese':         { cls:'badge-red',    color:'#ef4444', pct:90 },
};

function calcBmi(h, w) {
  if (!h || !w || h <= 0 || w <= 0) return null;
  const hm = h / 100;
  return Math.round((w / (hm * hm)) * 100) / 100;
}
function getBmiCategory(bmi) {
  if (bmi < 18.5) return 'Underweight';
  if (bmi < 25)   return 'Normal Weight';
  if (bmi < 30)   return 'Overweight';
  return 'Obese';
}

function updateLive() {
  const h = parseFloat(heightInput.value);
  const w = parseFloat(weightInput.value);
  const bmi = calcBmi(h, w);
  if (!bmi) { livePreview.style.display='none'; return; }
  const cat = getBmiCategory(bmi);
  const cfg = catConfig[cat];
  livePreview.style.display = 'block';
  liveBmiNum.textContent = bmi.toFixed(1);
  liveBmiNum.style.color = cfg.color;
  liveCat.textContent = cat;
  liveCat.className = 'badge ' + cfg.cls;
  liveFill.style.background = cfg.color;
  liveFill.style.width = cfg.pct + '%';
}

heightInput.addEventListener('input', updateLive);
weightInput.addEventListener('input', updateLive);

/* ── Animate saved gauge on page load ── */
window.addEventListener('load', () => {
  const fill = document.getElementById('savedGaugeFill');
  if (fill) {
    const pct = <?php echo $saved_cat ? ($cat_colors[$saved_cat]['pct'] ?? 50) : 0; ?>;
    setTimeout(() => { fill.style.width = pct + '%'; }, 200);
  }
});
</script>
</body>
</html>
