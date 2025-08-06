<?php

session_start();

if (!isset($_SESSION['user_user'])) {
    header("Location: login.php");
    exit;
}

require 'cron_manager.php';

$jobs = [
    [
        'name' => 'CONTRATOS_FATURA',
        'schedule' => '0 2 * * *',
        /*         'script' => '/opt/lampp/htdocs/WPP_PAINEL_3.1/CONTRATOS_FATURA.php',
 */
        'script' => '/opt/lampp/htdocs/CONTRATOS_FATURA.php',

        'enabled' => true,
        'label' => 'Buscar Contratos e Faturas'
    ],
    [
        'name' => 'ENVIOWPP_AVISO',
        'schedule' => '*/10 * * * *',
        /*         'script' => '/opt/lampp/htdocs/WPP_PAINEL_3.1/ENVIOWPP.php',
 */
        'script' => '/opt/lampp/htdocs/ENVIOWPP.php',

        'enabled' => true,
        'label' => 'Enviar Mensagens Aviso'
    ],
    [
        'name' => 'ENVIOWPP_VENCIDOS',
        'schedule' => '0 8 * * *',
        /*         'script' => '/opt/lampp/htdocs/WPP_PAINEL_3.1/ENVIOWPP_VENCIDOS.php', */
        'script' => '/opt/lampp/htdocs/ENVIOWPP_VENCIDOS.php',
        'enabled' => true,
        'label' => 'Enviar Mensagens de Vencidos'
    ]
];

$messages = [];
$currentCrons = [];
try {
    $currentCrons = CronManager::list();
} catch (Exception $e) {
    $messages[] = "<span class='error'>Não foi possível listar o crontab: {$e->getMessage()}</span>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $key    = $_POST['job_key'] ?? '';

    if (isset($jobs[$key])) {
        $job     = $jobs[$key];
        $pattern = '/' . preg_quote($job['script'], '/') . '/';

        try {
            CronManager::remove($pattern);

            if ($action === 'add' || $action === 'edit') {
                /*                 $time          = $_POST['time'][$key] ?? '00:00';
 */
                $interval      = max(0, (int)($_POST['interval'][$key] ?? 0));
                $days          = $_POST['days'][$key] ?? [];
                $everyMinute   = isset($_POST['every_minute'][$key]) && $_POST['every_minute'][$key] === '1';

                /*   list($h, $m) = explode(':', $time); */
                $h = $_POST['time_hour'][$key] ?? '00';
                $m = $_POST['time_minute'][$key] ?? '00';

                if ($everyMinute) {
                    if ($days) {
                        $dow = implode(',', array_map('intval', $days));
                        $expr = "* * * * {$dow}";
                    } else {
                        $expr = "* * * * *";
                    }
                } elseif ($days) {
                    $dow  = implode(',', array_map('intval', $days));
                    $expr = "{$m} {$h} * * {$dow}";
                } elseif ($interval > 0) {
                    $expr = "{$m} {$h} */{$interval} * *";
                } else {
                    $expr = "{$m} {$h} * * *";
                }

                $cmd = "{$expr} /opt/lampp/bin/php {$job['script']} >> /var/log/" . basename($job['script']) . ".log 2>&1";
                CronManager::add($cmd);
                $messages[] = "<span class='success'>Regra “{$job['label']}” ativada: <code>{$expr}</code></span>";
            } else {
                $messages[] = "<span class='info'>Regra “{$job['label']}” removida.</span>";
            }

            $currentCrons = CronManager::list();
        } catch (Exception $e) {
            $messages[] = "<span class='error'>Erro em “{$job['label']}”: {$e->getMessage()}</span>";
        }
    }
}

function old($field, $key, $default = '')
{
    return $_POST[$field][$key] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Crontab</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/crown.css">
</head>

<body>
    <div id="container">
        <div id="titulo">
            <h4><i class="fab fa-whatsapp"></i> SendNow 2.0</h4>
        </div>
        <?php include 'header.php'; ?>
        <div class="main-content">
            <div class="container">
                <h1>Gerenciar Crontab</h1>

                <?php if ($currentCrons): ?>
                    <div class="cron-list">
                        Cron atual:<br>
                        <pre><?= htmlspecialchars(implode("
", $currentCrons)) ?></pre>
                    </div>
                <?php endif ?>

                <?php if ($messages): ?>
                    <div class="messages">
                        <?php foreach ($messages as $msg) echo $msg; ?>
                    </div>
                <?php endif ?>

                <form method="POST">
                    <input type="hidden" name="action" id="form-action" value="">
                    <input type="hidden" name="job_key" id="form-job" value="">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Job</th>
                                    <th>Horário</th>
                                    <th>Intervalo (dias)</th>
                                    <th>Dias da Semana</th>
                                    <th>A cada minuto</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $key => $job): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($job['label']) ?></td>
                                        <td>
                                            <?php
                                            $defaultTime = explode(':', old('time', $key, '00:00'));
                                            $selectedHour = $defaultTime[0];
                                            $selectedMinute = $defaultTime[1];
                                            ?>
                                            <select name="time_hour[<?= $key ?>]">
                                                <?php for ($h = 0; $h < 24; $h++):
                                                    $val = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                                                    <option value="<?= $val ?>" <?= ($val == $selectedHour ? 'selected' : '') ?>><?= $val ?></option>
                                                <?php endfor ?>
                                            </select>
                                            :
                                            <select name="time_minute[<?= $key ?>]">
                                                <?php foreach (range(0, 50, 10) as $m):
                                                    $val = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                                    <option value="<?= $val ?>" <?= ($val == $selectedMinute ? 'selected' : '') ?>><?= $val ?></option>
                                                <?php endforeach ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="interval[<?= $key ?>]" value="<?= old('interval', $key, 0) ?>" min="0"></td>
                                        <td class="days-checkboxes">
                                            <?php
                                            $daysMap = ['0' => 'Dom', '1' => 'Seg', '2' => 'Ter', '3' => 'Qua', '4' => 'Qui', '5' => 'Sex', '6' => 'Sab'];
                                            foreach ($daysMap as $dVal => $dLabel):
                                                $chk = in_array($dVal, old('days', $key, [])) ? 'checked' : ''; ?>
                                                <label><input type="checkbox" name="days[<?= $key ?>][]" value="<?= $dVal ?>" <?= $chk ?>> <?= $dLabel ?></label>
                                            <?php endforeach ?>
                                        </td>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="every_minute[<?= $key ?>]" value="1" <?= old('every_minute', $key) ? 'checked' : '' ?>>
                                                A cada minuto
                                            </label>
                                        </td>
                                        <td class="actions">
                                            <button type="button" class="btn-add" onclick="submitForm('add','<?= $key ?>')">Salvar</button>
                                            <button type="button" class="btn-rem" onclick="submitForm('remove','<?= $key ?>')">Remover</button>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
        <script>
            function submitForm(action, jobKey) {
                document.getElementById('form-action').value = action;
                document.getElementById('form-job').value = jobKey;
                document.forms[0].submit();
            }
        </script>
    </div>
</body>

</html>