<?php
if (!defined('WEB_LOGUI')) die('File not included');
if (!isset($reportdata)) die("Unable to report mail");
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Redirecting...</title>
  </head>
  <body>
    <form action="https://report.halon.se/<?php echo $reportdata['type'] ?>/" id="reportform" method="post" target="_blank">
      <?php if (isset($reportdata['refid'])) { ?>
        <input type="hidden" name="refid" value="<?php echo htmlspecialchars($reportdata['refid']) ?>">
      <?php } ?>
      <?php
        if (isset($reportdata['messageid']) && isset($reportdata['actionid'])) {
      ?>
        <textarea name="email" style="display:none"><?php
        try {
          $response = $client->operation('/protobuf', 'POST', null, [
            'command' => 'F',
            'program' => 'smtpd',
            'payload' => [
              'conditions' => [
                'ids' => [
                  [
                    'transaction' => $reportdata['messageid'],
                    'queue' => $reportdata['actionid']
                  ]
                ]
              ]
            ]
          ]);
          if (!$response->body || !isset($response->body->items) || count($response->body->items) !== 1)
            throw new RestException(404, 'Not found');

          require_once 'inc/eml.php';
          echo htmlspecialchars(eml_download($client, $response->body->items[0]->hqfpath, $actionid, true, true));
        } catch (RestException $e) { die($e); }
        ?></textarea>
      <?php
        }
      ?>
      <script>
        window.onload = function() {
          document.getElementById("reportform").submit();
          history.back();
        }
      </script>
    </form>
  </body>
</html>
