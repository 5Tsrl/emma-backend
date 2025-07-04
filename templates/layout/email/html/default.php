<?php

use Cake\Core\Configure;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <!--[if !mso]><!-->
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <!--<![endif]-->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $this->fetch('title') ?></title>
  <!--[if (gte mso 9)|(IE)]>
    <style type="text/css">
        table {border-collapse: collapse;}
    </style>
    <![endif]-->
  <style type="text/css">
    /* Basics */
    body {
      margin: 0 !important;
      padding: 0;
      background-color: #ffffff;
      font-family: sans-serif;
    }

    table {
      border-spacing: 0;
      font-family: sans-serif;
      color: #333333;
    }

    td {
      padding: 0;
    }

    img {
      border: 0;
    }

    div[style*="margin: 16px 0"] {
      margin: 0 !important;
    }

    .wrapper {
      width: 100%;
      table-layout: fixed;
      -webkit-text-size-adjust: 100%;
      -ms-text-size-adjust: 100%;
    }

    .webkit {
      max-width: 600px;
      margin: 0 auto;
    }

    .outer {
      margin: 0 auto;
      width: 100%;
      max-width: 600px;
    }

    .full-width-image {
      width: 100%;
      max-width: 600px;
      height: auto;
      border-bottom: 4px solid #2e2b28;
      margin-bottom: 1em;
      padding-bottom: 1em;
      padding-top: 1em;
    }

    .inner {
      padding: 10px;
    }

    p {
      Margin: 0;
    }

    a {
      color: #2e2b28;
      text-decoration: underline;
    }

    .h1 {
      font-size: 21px;
      font-weight: bold;
      Margin-bottom: 18px;
    }

    .h2 {
      font-size: 18px;
      font-weight: bold;
      Margin-bottom: 12px;
    }

    /* One column layout */
    .one-column .contents {
      text-align: left;
    }

    .one-column p {
      Margin-bottom: 10px;
    }

    .area-riservata {
      padding-top: 2em;
      margin-top: 2em;
      border-top: 1px solid silver;
    }

    /* -------------------------------------
          BUTTONS
      ------------------------------------- */
    .btn {
      box-sizing: border-box;
      width: 100%;
      text-align: center;
    }

    .btn>tbody>tr>td {
      padding-bottom: 15px;
    }

    .btn table {
      width: auto;
    }

    .btn table td {
      background-color: #ffffff;
      border-radius: 5px;
      text-align: center;
    }

    .btn a {
      background-color: #ffffff;
      border: solid 1px #3A9FCF;
      border-radius: 5px;
      box-sizing: border-box;
      color: #3A9FCF;
      cursor: pointer;
      display: inline-block;
      font-size: 14px;
      font-weight: bold;
      margin: 0;
      padding: 12px 25px;
      text-decoration: none;
      text-transform: capitalize;
    }

    .btn-primary table td {
      background-color: #3A9FCF;
    }

    .btn-primary a {
      background-color: #3A9FCF;
      border-color: #3A9FCF;
      color: #ffffff;
    }

    .btn-success a {
      background-color: #40c200;
      border-color: #40c200;
      color: #ffffff;
    }

    .btn-danger a {
      background-color: #ff0000;
      border-color: #ff0000;
      color: #ffffff;
    }
  </style>
</head>

<body>
  <center class="wrapper">
    <div class="webkit">
      <!--[if (gte mso 9)|(IE)]>
				<table width="600" align="center">
				<tr>
				<td>
				<![endif]-->
      <table class="outer" align="center">
        <tr>
          <td class="full-width-image">
            <img src="cid:12345" alt="<?= Configure::read('VUE_APP_TITLE') ?>" width="150">
          </td>
        </tr>
        <tr>
          <td class="one-column">
            <table width="100%">
              <tr>
                <td class="inner contents">
                  <?= $this->Text->autoParagraph($this->fetch('content')); ?>
                </td>
              </tr>
            </table>
          </td>
        </tr>


        <tr>
          <td class="full-width-image">
            <hr>
            <small>
              <?= $this->cell('Footer',[$id ?? null]); ?>
            </small>
          </td>
        </tr>
        <tr>
      </table>
      <!--[if (gte mso 9)|(IE)]>
				</td>
				</tr>
				</table>
				<![endif]-->
    </div>
  </center>
</body>

</html>