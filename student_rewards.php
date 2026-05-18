<!DOCTYPE html>
<html>
<head>
    <title>My Rewards - CCS</title>
    <style>
        .rewards-container { max-width: 1000px; margin: 20px auto; padding: 20px; }
        .score-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .score-card { background: white; border-radius: 16px; padding: 25px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .score-card .percentage { font-size: 48px; font-weight: bold; }
        .score-card .label { color: #666; margin: 10px 0; }
        .total-score { background: linear-gradient(135deg, #1a1a2e, #16213e); color: white; border-radius: 20px; padding: 30px; text-align: center; margin-bottom: 30px; }
        .total-score h2 { font-size: 64px; margin: 10px 0; }
        .progress-bar { background: #e5e7eb; border-radius: 10px; overflow: hidden; height: 12px; margin-top: 15px; }
        .progress-fill { background: #10b981; height: 100%; width: 0%; transition: width 0.5s; }
        .rewards-list { background: white; border-radius: 16px; padding: 20px; }
        .reward-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eee; }
        .claim-btn { background: #10b981; color: white; border: none; padding: 8px 20px; border-radius: 25px; cursor: pointer; }
        .claim-btn.disabled { background: #ccc; cursor: not-allowed; }
    </style>
</head>
<body style="background:#f0f2f5; font-family:'Segoe UI',sans-serif;">
<div class="rewards-container">
    <h1>⭐ My Rewards Dashboard</h1>
    
    <?php
    // Example student data - replace with DB values
    $pointsEarned = 1850;      // Points from sit-ins (max 2000)
    $totalSessions = 42;       // Total sessions completed (max 50)
    $tasksCompleted = 16;       // Tasks done (max 20)
    
    // Calculate percentages (20% each category = max 20 points per category)
    $pointsScore = min(20, round(($pointsEarned / 2000) * 20));
    $sessionsScore = min(20, round(($totalSessions / 50) * 20));
    $tasksScore = min(20, round(($tasksCompleted / 20) * 20));
    $finalScore = $pointsScore + $sessionsScore + $tasksScore;
    ?>
    
    <div class="score-grid">
        <div class="score-card">
            <div class="percentage" style="color:#3b82f6"><?= $pointsScore ?>/20</div>
            <div class="label">🎯 Rewards/Points (20%)</div>
            <div class="progress-bar"><div class="progress-fill" style="width: <?= ($pointsScore/20)*100 ?>%"></div></div>
            <small><?= number_format($pointsEarned) ?> / 2000 points earned</small>
        </div>
        <div class="score-card">
            <div class="percentage" style="color:#10b981"><?= $sessionsScore ?>/20</div>
            <div class="label">📚 Total Sessions (20%)</div>
            <div class="progress-bar"><div class="progress-fill" style="width: <?= ($sessionsScore/20)*100 ?>%"></div></div>
            <small><?= $totalSessions ?> / 50 sessions completed</small>
        </div>
        <div class="score-card">
            <div class="percentage" style="color:#f59e0b"><?= $tasksScore ?>/20</div>
            <div class="label">✅ Tasks Completed (20%)</div>
            <div class="progress-bar"><div class="progress-fill" style="width: <?= ($tasksScore/20)*100 ?>%"></div></div>
            <small><?= $tasksCompleted ?> / 20 tasks done</small>
        </div>
    </div>
    
    <div class="total-score">
        <div>🏆 YOUR FINAL SCORE</div>
        <h2><?= $finalScore ?> / 60</h2>
        <div class="progress-bar"><div class="progress-fill" style="width: <?= ($finalScore/60)*100 ?>%; background:#f59e0b;"></div></div>
        <p><?= $finalScore >= 50 ? "🌟 Excellent! You're a top performer!" : ($finalScore >= 35 ? "👍 Good progress! Keep it up!" : "📚 Complete more sessions to earn rewards!") ?></p>
    </div>
    
    <div class="rewards-list">
        <h3>🎁 Available Rewards</h3>
        <div class="reward-item"><span>🏪 10% Off Canteen Voucher</span><button class="claim-btn" <?= $finalScore < 20 ? 'disabled class="disabled"' : '' ?>>Claim (20 pts)</button></div>
        <div class="reward-item"><span>📚 Free Printing (10 pages)</span><button class="claim-btn" <?= $finalScore < 35 ? 'disabled class="disabled"' : '' ?>>Claim (35 pts)</button></div>
        <div class="reward-item"><span>🎓 CCS Merit Certificate</span><button class="claim-btn" <?= $finalScore < 50 ? 'disabled class="disabled"' : '' ?>>Claim (50 pts)</button></div>
    </div>
</div>
</body>
</html>