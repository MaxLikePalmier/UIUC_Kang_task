<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Barcode Scanner Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        *,
        body {
            font-family: "Roboto", sans-serif;
            text-align: center;
        }

        .root {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 36px 24px;
        }

        .title {
            padding-top: 40px;
            font-weight: 700;
            font-size: 24px;
            line-height: 24px;
        }

        .user-name {
            padding-top: 40px;
            font-weight: 700;
            font-size: 18px;
            line-height: 24px;
            letter-spacing: 2%;
        }

        .user-role {
            padding-top: 4px;
            font-weight: 400;
            font-size: 16px;
            line-height: 16px;
        }

        .link-button {
            margin-top: 24px;
            display: inline-block;
            text-decoration: none;
            background: #2067D0;
            border-radius: 4px;
            color: #fff;
            padding: 16px;
            width: 100%;
            max-width: 400px;
            height: 56px;
            font-weight: 600;
            font-size: 18px;
            line-height: 24px;
            box-sizing: border-box;
        }

        .info {
            padding-top: 12px;
            font-weight: 400;
            font-size: 16px;
            line-height: 20px;
            color: #555555;
        }
    </style>
</head>

<body>
    <div class="root">
        <?php if ($logoUrl) : ?>
            <div>
                <img src="<?php echo esc_url($logoUrl); ?>" style="max-width: 80%; max-height: 80px;" />
            </div>
        <?php elseif ($blogName) : ?>
            <div style="font-size: 30px; line-height: 40px; font-weight: 700;">
                <?php echo esc_html($blogName); ?>
            </div>
        <?php endif; ?>
        <div class="title">Barcode Scanner<br />Login</div>
        <?php if ($fullName) : ?>
            <div class="user-name"><?php echo esc_html($fullName); ?></div>
            <div class="user-role">(<?php echo esc_html($user->user_login); ?>)</div>
        <?php else : ?>
            <div class="user-name"><?php echo esc_html($user->user_login); ?></div>
        <?php endif; ?>
        <div style="width: 100%;">
            <a class="link-button" href="<?php echo esc_url($link); ?>">Log into app</a>
        </div>
        <div class="info">
            Click on login button and confirm<br />
            to start Barcode Scanner application
        </div>
    </div>
</body>

</html>