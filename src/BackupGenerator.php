<?php

namespace Blocktrail\SDK;

use Blocktrail\SDK\Bitcoin\BIP32Key;
use Endroid\QrCode\QrCode;

/**
 * Class BackupGenerator
 */
class BackupGenerator {

    const QR_CODE_SIZE = 195;

    /**
     * path to fonts used for pdf generation
     *
     * @var string
     */
    protected $fontsPath;

    /**
     * array of data and QR codes for the blocktrail public keys
     *
     * @var null
     */
    protected $blocktrailPubKeyQRs = [];

    protected $identifier;

    protected $backupInfo;

    protected $extra;

    protected $options = [
        'page1' => true,
        'page2' => true,
        'page3' => true,
    ];

    /**
     * @param string $identifier
     * @param array  $backupInfo
     * @param array  $extra
     * @param null   $options
     */
    public function __construct($identifier, $backupInfo, $extra = null, $options = null) {
        /*
         * if DOMPDF is not already loaded we have to do it
         * they require a config file to be loaded, no autoloading :/
         */
        if (!defined('DOMPDF_ENABLE_AUTOLOAD')) {
            // disable DOMPDF's internal autoloader if you are using Composer
            define('DOMPDF_ENABLE_AUTOLOAD', false);

            //try the different possible locations for the config file, depending on if the sdk is included as a dependency or is the main project itself
            (@include_once __DIR__ . '/../../../dompdf/dompdf/dompdf_config.inc.php') || @include_once __DIR__ . '/../vendor/dompdf/dompdf/dompdf_config.inc.php';
        }

        //set the fonts path
        $this->fontsPath = dirname(__FILE__) . '/../resources/fonts';

        $this->identifier = $identifier;
        $this->backupInfo = $backupInfo;
        $this->extra = $extra ?: [];
        $this->options = array_merge($this->options, $options);
    }

    /**
     * process the blocktrail public keys and create qr codes for each one
     */
    protected function processBlocktrailPubKeys() {
        if (!isset($this->backupInfo['blocktrail_public_keys'])) {
            return;
        }

        //create QR codes for each blocktrail pub key
        foreach ($this->backupInfo['blocktrail_public_keys'] as $keyIndex => $key) {
            $key = $key instanceof BIP32Key ? $key : BIP32Key::create($key);
            $qrCode = new QrCode();
            $qrCode
                ->setText($key->key())
                ->setSize(self::QR_CODE_SIZE-20)
                ->setPadding(10)
                ->setErrorCorrection('high')
                ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
                ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
                ->setLabel("KeyIndex: ".$keyIndex."    Path: ".$key->path())
                ->setLabelFontSize(10)
            ;
            $this->blocktrailPubKeyQRs[] = array(
                'keyIndex'  => $keyIndex,
                'path'      => $key->path(),
                'qr'        => $qrCode->getDataUri(),
                'qrImg'     => $qrCode->getImage(),
            );
        }
    }

    /**
     * generate html document of backup details
     *
     * @return string
     */
    public function generateHTML() {
        //create blocktrail pub key QR codes if not already done
        if (!$this->blocktrailPubKeyQRs) {
            $this->processBlocktrailPubKeys();
        }
        $pubKeysHtml = "";
        foreach ($this->blocktrailPubKeyQRs as $pubKey) {
            $pubKeysHtml .= "<img src='{$pubKey['qr']}' />";
        }
        $totalPubKeys = count($this->blocktrailPubKeyQRs);

        $backupInfo = $this->prepareBackupInfo();
        $extraInfo = $this->prepareExtraInfo();

        $passwordEncryptedSecret = null;
        if (isset($this->backupInfo['encrypted_secret'])) {
            $passwordEncryptedSecret = $this->backupInfo['encrypted_secret'];
        }

        $html = self::renderTemplate(__DIR__ . "/../resources/templates/recovery_sheet.html.php", [
            'identifier' => $this->identifier,
            'totalPubKeys' => $totalPubKeys,
            'pubKeysHtml' => $pubKeysHtml,
            'backupInfo' => $backupInfo,
            'extraInfo' => $extraInfo,
            'passwordEncryptedSecret' => $passwordEncryptedSecret,
            'options' => $this->options,
        ]);

        return $html;
    }

    protected function prepareBackupInfo() {
        $backupInfo = [];

        if (isset($this->backupInfo['primary_mnemonic'])) {
            $backupInfo[] = [
                'title' => 'Primary Mnemonic',
                'mnemonic' => $this->backupInfo['primary_mnemonic'],
            ];
        }
        if (isset($this->backupInfo['backup_mnemonic'])) {
            $backupInfo[] = [
                'title' => 'Primary Mnemonic',
                'mnemonic' => $this->backupInfo['backup_mnemonic'],
            ];
        }
        if (isset($this->backupInfo['encrypted_primary_seed'])) {
            $backupInfo[] = [
                'title' => 'Encrypted Primary Seed',
                'mnemonic' => $this->backupInfo['encrypted_primary_seed'],
            ];
        }
        if (isset($this->backupInfo['backup_seed'])) {
            $backupInfo[] = [
                'title' => 'Backup Seed',
                'mnemonic' => $this->backupInfo['backup_seed'],
            ];
        }
        if (isset($this->backupInfo['recovery_encrypted_secret'])) {
            $backupInfo[] = [
                'title' => 'Encrypted Recovery Secret',
                'mnemonic' => $this->backupInfo['recovery_encrypted_secret'],
            ];
        }

        return $backupInfo;
    }

    protected function prepareExtraInfo() {
        $extraInfo = [];

        foreach ($this->extra as $k => $v) {
            if (is_array($v)) {
                $extraInfo[] = $v;
            } else {
                $extraInfo[] = ['title' => $k, 'value' => $v];
            }
        }

        return $extraInfo;
    }

    public static function renderTemplate($file, $vars) {
        if (is_array($vars) && !empty($vars)) {
            extract($vars);
        }

        ob_start();

        include $file;

        return ob_get_clean();
    }

    /**
     * generate PDF document of backup details
     * @return string       pdf data, ready to be saved to file or streamed to browser
     */
    public function generatePDF() {
        $dompdf = new \DOMPDF();
        $html = $this->generateHTML();
        $dompdf->load_html($html);

        $dompdf->render();
        $pdfStream = $dompdf->output();
        return $pdfStream;
    }

    /**
     * generate text document of backup details (not implemented yet)
     *
     */
    public function generateTxt() {
        //...
    }
}
