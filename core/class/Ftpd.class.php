<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../../../../core/php/core.inc.php';
include_file('core', 'Ftpd', 'class', 'FtpdConstants');

class Ftpd extends eqLogic
{
    public static $_widgetPossibility = array('custom' => true);

    /**
         * Obtenir l'état du daemon
         *
         * @return [log] message de log
         *         [state]  ok  Démarré
         *                  nok Non démarré
         *         [launchable] ok  Démarrable
         *                      nok Non démarrable
         *         [launchable_message] Cause de non démarrage
         *         [auto]   0 Démarrage automatique désactivé
         *                  1 Démarrage automatique activé
         */
    public static function deamon_info($Ftpd_path)
    {
        $return = array();
        $return['log'] = '';
        $return['state'] = 'nok';
        $return['launchable'] = 'ok';
        $pid_file = $Ftpd_path . "/Ftpd.pid";
        if (file_exists($pid_file)) {
            if (posix_getsid(trim(file_get_contents($pid_file)))) {
                $return['state'] = 'ok';
            } else {
                log::add('Ftpd', 'debug', __('Process non trouvé', __FILE__));
            }
        } else {
            $processlist = system::ps("python ./Ftpd.py start");
            if (count($processlist) > 0) {
                foreach ($processlist as $value) {
                    log::add('Ftpd', 'debug', __('Retrouve Ftpd.py processus avec PID : ', __FILE__) . $value["pid"]);
                    $return['state'] = 'ok';
                }
            }
        }
        if (config::byKey("internalAddr") == '' ) {
            $return['launchable_message'] = __('Adresse non configurée', __FILE__);
            $return['launchable'] = 'nok';
        }
        if (config::byKey("internalPort") == "") {
            $return['launchable_message'] = __('Port non configuré', __FILE__);
            $return['launchable'] = 'nok';
        }
        return $return;
    }

    /**
         * Genere la configuration du daemon
         *
         * @param Debug (par défault désactivé)
         */
    public static function deamonGenereConfiguration($_debug = false)
    {
        log::add('Ftpd', 'debug', __('Prepare conf daemon', __FILE__));
        $xml = false;
        if (file_exists($Ftpd_path . '/Ftpd.xml')) {
            $xml = simplexml_load_file($Ftpd_path . '/Ftpd.xml');
        }
        if ($xml === false) {
            $xml = new SimpleXMLElement('<config/>');
            log::add('Ftpd', 'debug', 'New config');
        }

        if (!isset($xml->daemon)) {
            $daemon = $xml->addChild('daemon');
            log::add('Ftpd', 'debug', 'New daemon');
        } else {
            $daemon = $xml->daemon;
        }
        if (!isset($daemon->port)) {
            $daemon->addChild('port', config::byKey('port', 'Ftpd', FtpdConstants::DEFAULT_PORT));
        } else {
            $daemon->port = config::byKey('port', 'Ftpd', FtpdConstants::DEFAULT_PORT);
        }
        if (!isset($daemon->port)) {
            $daemon->addChild('port', config::byKey('port', 'Ftpd', FtpdConstants::DEFAULT_PORT));
        } else {
            $daemon->port = config::byKey('port', 'Ftpd', FtpdConstants::DEFAULT_PORT);
        }
        if (!isset($daemon->local_ip)) {
            $daemon->addChild('local_ip', config::byKey('local_ip', 'Ftpd', FtpdConstants::DEFAULT_LOCAL_IP));
        } else {
            $daemon->local_ip = config::byKey('local_ip', 'Ftpd', FtpdConstants::DEFAULT_LOCAL_IP);
        }
        if (!isset($daemon->authorized_ip)) {
            $daemon->addChild('authorized_ip', config::byKey('authorized_ip', 'Ftpd', ''));
        } else {
            $daemon->authorized_ip = config::byKey('authorized_ip', 'Ftpd', '');
        }
        $logFile = dirname(__FILE__) . '/../../../../log/Ftpd_daemon';
        if (!isset($daemon->log_file)) {
            $daemon->addChild('log_file', $logFile);
        } else {
            $daemon->log_file = $logFile;
        }
        if (!isset($daemon->api_key)) {
            $daemon->addChild('api_key', jeedom::getApiKey('Ftpd'));
        } else {
            $daemon->api_key = jeedom::getApiKey('Ftpd');
        }
        $_CaptureDir = calculPath(config::byKey('recordDir', 'Ftpd'));
        if (!is_dir($_CaptureDir)) {
            log::add('Ftpd', 'debug', 'mkdir ' . $_CaptureDir);
            if (mkdir($_CaptureDir, 0777, true) === false) {
                log::add('Ftpd', 'error', __('Impossible de creer le dossier ', __FILE__) . $_CaptureDir);
            }
        } else {
            if (!is_writable($_CaptureDir)) {
                log::add('Ftpd', 'error', __('Impossible d\'ecrire dans le dossier ', __FILE__) . $_CaptureDir);
            }
        }
        if (!isset($daemon->ftp_dir)) {
            $daemon->addChild('ftp_dir', $_CaptureDir . '/');
        } else {
            $daemon->ftp_dir = $_CaptureDir . '/';
        }
        if (!isset($daemon->pid_file)) {
            $daemon->addChild('pid_file', $Ftpd_path . "/Ftpd.pid");
        } else {
            $daemon->pid_file = $Ftpd_path . "/Ftpd.pid";
        }
        if (!isset($daemon->debug)) {
            $daemon->addChild('debug', config::byKey('debug', 'Ftpd', '0'));
        } else {
            $daemon->debug = config::byKey('debug', 'Ftpd', '0');
        }
        $pathjeedom = config::byKey("internalComplement");
        if (substr($pathjeedom, 0, 1) != "/") {
            $pathjeedom = "/" . $pathjeedom;
        }
        if (substr($pathjeedom, -1) != "/") {
            $pathjeedom = $pathjeedom . "/";
        }
        if (!isset($daemon->internalProtocol)) {
            $daemon->addChild('internalProtocol', config::byKey('internalProtocol', 'core', 'http://'));
        } else {
            $daemon->internalProtocol = config::byKey('internalProtocol', 'core', 'http://');
        }
        if (!isset($daemon->internalPort)) {
            $daemon->addChild('internalPort', config::byKey('internalPort', 'core', '80'));
        } else {
            $daemon->internalPort = config::byKey('internalPort', 'core', '80');
        }
        if (config::byKey("internalComplement") != "") {
            if (!isset($daemon->internalComplement)) {
                $daemon->addChild('internalComplement', config::byKey('internalComplement', 'core', ''));
            } else {
                $daemon->internalComplement = config::byKey('internalComplement', 'core', '');
            }
        } else {
            unset($daemon->internalComplement);
        }
        if (isset($daemon->url_new_capture)) {
            unset($daemon->url_new_capture);
        }
        if (isset($daemon->url_force_scan)) {
            unset($daemon->url_force_scan);
        }
        file_put_contents($Ftpd_path . '/ressources/Ftpd.xml', $xml->asXML());
    }

    /**
         * Démarre le daemon
         *
         * @param Debug (par défault désactivé)
         */
    public static function deamon_start($_debug = false)
    {
        self::deamon_stop();
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok') {
            throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
        }
        self::deamonGenereConfiguration($Ftpd_path);
        $Ftpd_path = dirname(__FILE__) . '/../../ressources';
        $cmd = "cd " . $Ftpd_path . ";python ./Ftpd.py start";
        log::add('Ftpd', 'info', __('daemon start : ', __FILE__) . $cmd);
        Ftpd::exec($cmd);
        sleep(5);
        $deamon_info = self::deamon_info();
    }

    /**
         * Arrête le daemon
         */
    public static function deamon_stop()
    {
        // Initialisation de la connexion
        $Ftpd_path = dirname(__FILE__) . '/../../ressources';
        $pid_file = $Ftpd_path . "/Ftpd.pid";
        if (!file_exists($pid_file)) {
            log::add('Ftpd', 'debug', __('Pid file not found', __FILE__));
            $processlist = system::ps("python ./Ftpd.py start");
            if (count($processlist) > 0) {
                foreach ($processlist as $value) {
                    log::add('Ftpd', 'debug', __('Retrieve Ftpd.py process and kill with PID : ', __FILE__) . $value["pid"]);
                    exec("kill " . $value["pid"]);
                }
            }
            $processlist = system::ps("python ./Ftpd.py start");
            while (count($processlist) > 0) {
                sleep(5);
                $processlist = system::ps("python ./Ftpd.py start");
            }
            log::add('Ftpd', 'debug', __('Ftpd.py process stoped', __FILE__));
        } else {
            $pid = trim(file_get_contents($pid_file));
            if (!posix_getsid($pid)) {
                log::add('Ftpd', 'debug', __('Process not found', __FILE__) . " (" . $pid . ")");
            } else {
                $cmd = "cd " . $Ftpd_path . ";python ./Ftpd.py stop";
                log::add('Ftpd', 'info', 'daemon stop');
                Ftpd::exec($cmd);
                sleep(10);
            }
        }
    }

    /**
         * Appel system
         *
         * @param commande
         *
         * @return code retour de la commande
         */
    public static function exec($commande)
    {
        $descriptorspec = array(
            0 => array("pipe", "r"), // stdin
            1 => array("pipe", "w"), // stdout
            2 => array("pipe", "w"), // stderr
        );
        $Ftpd_path = dirname(__FILE__) . '/../../ressources';
        $process = proc_open($commande, $descriptorspec, $pipes, $Ftpd_path, null);
        $stdout = stream_get_contents($pipes[1]);
        foreach (explode("\n", $stdout) as $line) {
            if ($line != "") {
                log::add('Ftpd', 'debug', 'daemon stdout : ' . $line);
            }
        }
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        foreach (explode("\n", $stderr) as $line) {
            if ($line != "") {
                log::add('Ftpd', 'debug', 'daemon stderr : ' . $line);
            }
        }
        fclose($pipes[2]);
        return proc_close($process);
    }

    /**
         * Force une détection des clients ayant déposé des fichiers
         */
    public static function forceDetectFtpd()
    {
        // Initialisation de la connexion
        log::add('Ftpd', 'info', 'forceDetectFtpd');
        $_CaptureDir = calculPath(config::byKey('recordDir', 'Ftpd'));
        if (is_dir($_CaptureDir)) {
            if ($dh = opendir($_CaptureDir)) {
                while (($file = readdir($dh)) !== false) {
                    if (is_dir($_CaptureDir . '/' . $file) && $file != "." && $file != ".." && substr($file, 0, 1) != ".") {
                        log::add('Ftpd', 'debug', 'Find Ftpd : ' . $file);
                        if (!is_object(self::byLogicalId($file, 'Ftpd'))) {
                            $eqLogic = new Ftpd();
                            $eqLogic->setLogicalId($file);
                            $eqLogic->setName($file);
                            $eqLogic->setEqType_name('Ftpd');
                            $eqLogic->setIsEnable(1);
                            $eqLogic->save();
                            message::add('Ftpd',__('Création d\'un equipement ftp : ', __FILE__) . $file, '', 'noMessage' . $eqLogic->getId());
                        }
                    }
                }
                closedir($dh);
            }
        }
    }

    public function postInsert()
    {
        $this->postUpdate();
    }

    /**
         * Force la variable recordDir à être valorisé
         */
    public static function postConfig_recordDir()
    {
        if (config::byKey('recordDir', 'Ftpd') == '') {
            config::save('recordDir', jeedom::getTmpFolder('Ftpd') . '/Ftpd_records', 'Ftpd');
        }
    }

    /**
         * Force la migration des equipements Ftpd en Ftpd
         */
    public static function migrePlugin()
    {
        config::save('port', config::byKey('port', 'Ftpd', DEFAULT_PORT), 'Ftpd');
        config::save('local_ip', config::byKey('local_ip', 'Ftpd', DEFAULT_LOCAL_IP), 'Ftpd');
        config::save('authorized_ip', config::byKey('authorized_ip', 'Ftpd', ''), 'Ftpd');
        config::save('recordDir', jeedom::getTmpFolder('Ftpd') . '/Ftpd_records', 'Ftpd');
        config::save('api', jeedom::getApiKey('Ftpd'), 'Ftpd');
        config::save('api::Ftpd::mode', config::byKey('api::Ftpd::mode'));
        foreach (eqLogic::byType('Ftpd') as $eqLogic) {
            $eqLogic->setEqType_name('Ftpd');
    		$eqLogic->save();
    		foreach (cmd::byEqLogicId($SubeqLogic->getId()) as $cmd) {
    			$cmd->setEqType('Ftpd');
    			$cmd->save();
    		}
        }
        $plugin = plugin::byId('ftpd');
        $plugin->deamon_stop();
        $plugin->setIsEnable(0);
        $plugin = plugin::byId('Ftpd');
        $plugin->deamon_start();
        $plugin->setIsEnable(1);
    }

    public function postUpdate()
    {
        $restart = false;
        foreach ($this->getCmd() as $cmd) {
            if ($cmd->getLogicalId() == '') {
                $cmd->setLogicalId('pattern');
                $cmd->save();
            }
        }
        foreach ($this->getCmd(null, 'pattern', null, true) as $cmd) {
            if ($cmd->getName() == 'Etat') {
                $cmd->setLogicalId('state');
                $cmd->save();
                $restart = true;
            }
            if ($cmd->getName() == 'Nom du dernier fichier') {
                $cmd->setLogicalId('lastfilename');
                $cmd->save();
                $restart = true;
            }
        }
        $state = $this->getCmd(null, 'state');
        if (!is_object($state)) {
            $state = new FtpdCmd();
            $state->setName(__('Etat', __FILE__));
            $state->setEqLogic_id($this->getId());
            $state->setType('info');
            $state->setSubType('binary');
            $state->setLogicalId('state');
            $state->setDisplay('invertBinary', 1);
            $state->setDisplay('generic_type', 'PRESENCE');
            $state->setTemplate('dashboard', 'presence');
            $state->setTemplate('mobile', 'presence');
            $state->save();
            $restart = true;
        }
        $lastfilename = $this->getCmd(null, 'lastfilename');
        if (!is_object($lastfilename)) {
            $lastfilename = new FtpdCmd();
            $lastfilename->setName(__('Nom du dernier fichier', __FILE__));
            $lastfilename->setEqLogic_id($this->getId());
            $lastfilename->setType('info');
            $lastfilename->setSubType('string');
            $lastfilename->setLogicalId('lastfilename');
            $lastfilename->setTemplate('dashboard', 'lastfilename');
            $lastfilename->setTemplate('mobile', 'lastfilename');
            $lastfilename->save();
            $restart = true;
        } else {
            $lastfilename->setTemplate('dashboard', 'lastfilename');
            $lastfilename->setTemplate('mobile', 'lastfilename');
            $lastfilename->save();
        }
        $notifyCmd = $this->getCmd(null, 'notify');
        if (!is_object($notifyCmd)) {
            $notifyCmd = new FtpdCmd();
            $notifyCmd->setName('Notification');
            $notifyCmd->setEqLogic_id($this->getId());
            $notifyCmd->setType('info');
            $notifyCmd->setSubType('binary');
            $notifyCmd->setLogicalId('notify');
            $notifyCmd->setEventOnly(1);
            $notifyCmd->setIsVisible(1);
            $notifyCmd->setTemplate('dashboard', 'notify');
            $notifyCmd->setTemplate('mobile', 'notify');
            $notifyCmd->save();
            $notifyCmd->setCollectDate('');
            $notifyCmd->event(1);
        }
        $notifyCommuteCmd = $this->getCmd(null, 'notify_commute');
        if (!is_object($notifyCommuteCmd)) {
            $notifyCommuteCmd = new FtpdCmd();
            $notifyCommuteCmd->setName(__('Bascule notification', __FILE__));
            $notifyCommuteCmd->setIsVisible(0);
            $notifyCommuteCmd->setEqLogic_id($this->getId());
            $notifyCommuteCmd->setType('action');
            $notifyCommuteCmd->setSubType('other');
            $notifyCommuteCmd->setLogicalId('notify_commute');
            $notifyCommuteCmd->setEventOnly(1);
            $notifyCommuteCmd->setValue($notifyCmd->getId());
            $notifyCommuteCmd->save();
        } else {
            $notifyCommuteCmd->setValue($notifyCmd->getId());
            $notifyCommuteCmd->save();
        }
        $notifyOnCmd = $this->getCmd(null, 'notify_on');
        if (!is_object($notifyOnCmd)) {
            $notifyOnCmd = new FtpdCmd();
            $notifyOnCmd->setName(__('Active notification', __FILE__));
            $notifyOnCmd->setEqLogic_id($this->getId());
            $notifyOnCmd->setType('action');
            $notifyOnCmd->setSubType('other');
            $notifyOnCmd->setLogicalId('notify_on');
            $notifyOnCmd->setEventOnly(1);
            $notifyOnCmd->setIsVisible(0);
            $notifyOnCmd->setValue($notifyCmd->getId());
            $notifyOnCmd->save();
        } else {
            $notifyOnCmd->setValue($notifyCmd->getId());
            $notifyOnCmd->save();
        }
        $notifyOffCmd = $this->getCmd(null, 'notify_off');
        if (!is_object($notifyOffCmd)) {
            $notifyOffCmd = new FtpdCmd();
            $notifyOffCmd->setName(__('Désactive notification', __FILE__));
            $notifyOffCmd->setEqLogic_id($this->getId());
            $notifyOffCmd->setType('action');
            $notifyOffCmd->setSubType('other');
            $notifyOffCmd->setLogicalId('notify_off');
            $notifyOffCmd->setEventOnly(1);
            $notifyOffCmd->setIsVisible(0);
            $notifyOffCmd->setValue($notifyCmd->getId());
            $notifyOffCmd->save();
        } else {
            $notifyOffCmd->setValue($notifyCmd->getId());
            $notifyOffCmd->save();
        }
        $recordStateCmd = $this->getCmd(null, 'recordState');
        if (!is_object($recordStateCmd)) {
            $recordStateCmd = new FtpdCmd();
            $recordStateCmd->setIsVisible(1);
            $recordStateCmd->setName(__('Status d enregistrement', __FILE__));
            $recordStateCmd->setType('info');
            $recordStateCmd->setLogicalId('recordState');
            $recordStateCmd->setEqLogic_id($this->getId());
            $recordStateCmd->setSubType('binary');
            $recordStateCmd->setDisplay('generic_type', 'CAMERA_RECORD_STATE');
            $recordStateCmd->setCollectDate('');
            $recordStateCmd->event(1);
            $recordStateCmd->save();
            $recordStateCmd->setCollectDate('');
            $recordStateCmd->event(1);
        }

        $stopRecordCmd = $this->getCmd(null, 'stopRecordCmd');
        if (!is_object($stopRecordCmd)) {
            $stopRecordCmd = new FtpdCmd();
            $stopRecordCmd->setName(__('Arrêter l enregistrement', __FILE__));
            $stopRecordCmd->setType('action');
            $stopRecordCmd->setLogicalId('stopRecordCmd');
            $stopRecordCmd->setEqLogic_id($this->getId());
            $stopRecordCmd->setSubType('other');
            $stopRecordCmd->setOrder(999);
            $stopRecordCmd->setDisplay('icon', '<i class="fa fa-stop"></i>');
            $stopRecordCmd->setDisplay('generic_type', 'CAMERA_STOP');
            $stopRecordCmd->save();
        }

        $startRecordCmd = $this->getCmd(null, 'startRecordCmd');
        if (!is_object($startRecordCmd)) {
            $startRecordCmd = new FtpdCmd();
            $startRecordCmd->setName(__('Démarrer l enregistrement', __FILE__));
            $startRecordCmd->setType('action');
            $startRecordCmd->setLogicalId('startRecordCmd');
            $startRecordCmd->setEqLogic_id($this->getId());
            $startRecordCmd->setSubType('other');
            $startRecordCmd->setOrder(999);
            $startRecordCmd->setDisplay('icon', '<i class="fa fa-circle"></i>');
            $startRecordCmd->setDisplay('generic_type', 'CAMERA_START');
            $startRecordCmd->save();
        }

        if ($restart) {
            $plugin = plugin::byId('Ftpd');
            $plugin->deamon_start();
        }
    }

    public function preRemove()
    {
        $this->removeAllSnapshot(true);
    }

    public function removeAllSnapshot($anddir = false)
    {
        log::add('Ftpd', 'debug', "Remove All Snapshot");
        $_CaptureDir = calculPath(config::byKey('recordDir', 'Ftpd')) . '/' . $this->getLogicalId();
        if ($handle = opendir($_CaptureDir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    unlink($_CaptureDir . "/" . $file);
                }
            }
            closedir($handle);
            if ($anddir)
                rmdir($_CaptureDir);
        }
    }

    public static function removeSnapshot($file)
    {
        log::add('Ftpd', 'debug', "Remove Snapshot " . $file);
        $record_dir = calculPath(config::byKey('recordDir', 'Ftpd'));
        unlink($record_dir . '/' . $file);
        $path_parts = pathinfo($file);
        if (strpos(mime_content_type($file), 'video') !== false) {
            $file = $path_parts['filename'] . '_mini.jpg';
            log::add('Ftpd', 'debug', "Remove Snapshot mini " . $file);
            unlink($_CaptureDir . "/" . $file);
        }
    }

    public function newcapture($filename, $orginalfilname)
    {
        if ($this->getIsEnable()) {
            $recordState = $this->getCmd(null, 'recordState');
            if ($recordState->execCmd() == 0) {
                log::add('Ftpd', 'debug', "Do not record push notification for " . $this->getLogicalId() . " " . $filename . " " . $orginalfilname);
                unlink(calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId() . "/" . $filename));
                return true;
            }
            log::add('Ftpd', 'info', "Receive push notification for " . $this->getLogicalId() . " " . $filename . " " . $orginalfilname);
            $state = $this->getCmd(null, 'state');
            $lastfilename = $this->getCmd(null, 'lastfilename');
            $lastfilename->setCollectDate('');
            $lastfilename->event(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId() . "/" . $filename);
            $state->setCollectDate('');
            $state->event(1);
            foreach ($this->getCmd(null, 'pattern', null, true) as $cmd) {
                log::add('Ftpd', 'debug', $cmd->getName() . " : " . $cmd->getConfiguration('pattern') . " match ? " . $orginalfilname);
                if (preg_match($cmd->getConfiguration('pattern'), $orginalfilname)) {
                    log::add('Ftpd', 'info', "match with " . $cmd->getName());
                    $cmd->setCollectDate('');
                    $cmd->event(1);
                }
            }
            $path_parts = pathinfo(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId() . "/" . $filename);
            if (strpos(mime_content_type(calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId() . "/" . $filename)), 'video') !== false) {
                # Convertion en mini
                log::add('Ftpd', 'debug', 'Convertion de l image en miniature');
                $cmd = 'ffmpeg -i ' . calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId() . "/" . $filename) . ' -r 1 -s 320x200 -frames:v 1 ' . calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId()) . "/" . $path_parts['filename'] . '_mini.jpg';
                log::add('Ftpd', 'debug', $cmd);
                exec($cmd);
            } else {
                list($width, $height) = getimagesize(calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId()) . "/" . $filename);

                if ($width > 150) {
                    log::add('Ftpd', 'debug', 'Creation de la miniature');
                    $tmpfname = calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId()) . "/" . $path_parts['filename'] . '_mini.jpg';
                    $modwidth = 150;
                    //$width * $size;
                    $modheight = round($height / $width * $modwidth);
                    //$height * $size;
                    // Resizing the Image
                    $tn = imagecreatetruecolor($modwidth, $modheight);
                    $image = imagecreatefromjpeg(calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId()) . "/" . $filename);
                    imagecopyresampled($tn, $image, 0, 0, 0, 0, $modwidth, $modheight, $width, $height);
                    // Outputting a .jpg, you can make this gif or png if you want
                    //notice we set the quality (third value) to 100
                    //imagejpeg($tn, null, 80);
                    imagejpeg($tn, $tmpfname, 80);
                    imagedestroy($tn);
                }
            }
            $notifyCmd = $this->getCmd(null, 'notify');
            log::add('Ftpd', 'debug', 'Notification ? ' . $notifyCmd->execCmd() . ' dest : ' . $notifyCmd->getConfiguration('notify_dest'));

            if ($notifyCmd->execCmd() == 1 && $notifyCmd->getConfiguration('notify_dest') != "") {
                $_options['title'] = '[Jeedom][Ftpd] ' . __('Détection sur la camera ', __FILE__) . $this->getHumanName();
                $_options['message'] = __('La camera a détecté un mouvement.', __FILE__) . ' ' . __('Voici le snapshot qui a ete pris', __FILE__);
                $_options['files'] = array();
                if (strpos(mime_content_type(calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId() . "/" . $filename)), 'video') !== false) {
                    $filename = calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId()) . "/" . $path_parts['filename'] . '_mini.jpg';
                } else {
                    if ($notifyCmd->getConfiguration('notify_reduce') == 1) {
                        array_push($_options['files'], calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId()) . "/" . $path_parts['filename'] . '_mini.jpg');
                    } else {
                        array_push($_options['files'], calculPath(config::byKey('recordDir', 'Ftpd') . '/' . $this->getLogicalId()) . "/" . $filename);
                    }
                }
                log::add('Ftpd', 'debug', 'Envoie d\'un message avec la derniere capture : ' . json_encode($_options['files']));
                foreach (explode(',', $notifyCmd->getConfiguration('notify_dest')) as $id) {
                    $cmd = cmd::byId(str_replace('#', '', $id));
                    if (is_object($cmd)) {
                        log::add('Ftpd', 'debug', 'Envoie du message avec ' . $cmd->getHumanName());
                        $cmd->execute($_options);
                    }
                }
            }
            // Nettoye les vieux fichiers
            $files = array();
            $_CaptureDir = calculPath(config::byKey('recordDir', 'Ftpd')) . '/' . $this->getLogicalId();

            if ($handle = opendir($_CaptureDir)) {
                while (false !== ($file = readdir($handle))) {
                    if ($file != "." && $file != ".." && !strpos($file, '_mini.jpg')) {
                        $files[filemtime($_CaptureDir . "/" . $file)] = $file;
                    }
                }
                closedir($handle);
            }
            if (count($files) > $this->getConfiguration('nbfilemax', 10)) {
                // sort
                ksort($files);
                $filetodelete = count($files) - $this->getConfiguration('nbfilemax', 10);
                foreach ($files as $file) {
                    if ($filetodelete > 0) {
                        log::add('Ftpd', 'debug', "delete " . $file);
                        unlink($_CaptureDir . "/" . $file);
                        $path_parts = pathinfo($file);

                        $file = $path_parts['filename'] . '_mini.jpg';
                        if (file_exists($_CaptureDir . "/" . $file)) {
                            log::add('Ftpd', 'debug', "delete " . $file);
                            unlink($_CaptureDir . "/" . $file);
                        }
                    }
                    $filetodelete--;
                }
            }
            sleep($this->getConfiguration('delairesetstatus', 10));
            //Reset l'état de l'équipement
            $state->setCollectDate('');
            $state->event(0);
            foreach ($this->getCmd(null, 'pattern', null, true) as $cmd) {
                if (preg_match($cmd->getConfiguration('pattern'), $orginalfilname)) {
                    $cmd->setCollectDate('');
                    $cmd->event(0);
                }
            }
        } else {
            log::add('Ftpd', 'info', "Equipement not enable. Del " . $this->getLogicalId() . " " . $filename . " " . $orginalfilname);
            unlink(calculPath(config::byKey('recordDir', 'Ftpd')) . '/' . $this->getLogicalId() . "/" . $filename);
        }
    }

    public static function compilationOk()
    {
        $Ftpd_path = dirname(__FILE__) . '/../../ressources';
        $cmd = "cd " . $Ftpd_path . ";python ./Ftpd.py test 2>/dev/null 1>&2";
        system($cmd, $code);
        log::add('Ftpd', 'debug', 'daemon test return ' . $code);
        if ($code == 0) {
            return true;
        } else {
            return false;
        }
    }

    public static function dependancy_info()
    {
        $return = array();
        $return['log'] = 'Ftpd_update';
        $return['progress_file'] = '/tmp/Ftpd_in_progress';
        $return['state'] = (self::compilationOk()) ? 'ok' : 'nok';
        return $return;
    }

    public static function dependancy_install()
    {
        if (file_exists('/tmp/Ftpd_in_progress')) {
            return;
        }
        log::remove('Ftpd_update');
        $cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../ressources/install.sh';
        $cmd .= ' >> ' . log::getPathToLog('Ftpd_update') . ' 2>&1 &';
        exec($cmd);
    }

    public function getLastCapture()
    {

        if ($this->getIsEnable()) {
            // Liste les fichiers
            $files = array();
            $_CaptureDir = calculPath(config::byKey('recordDir', 'Ftpd')) . '/' . $this->getLogicalId();
            if ($handle = opendir($_CaptureDir)) {
                while (false !== ($file = readdir($handle))) {
                    if ($file != "." && $file != ".." && !strpos($file, '_mini.jpg')) {
                        $files[filemtime($_CaptureDir . "/" . $file)] = $file;
                    }
                }
                closedir($handle);
            }
            ksort($files);
            return calculPath(config::byKey('recordDir', 'Ftpd')) . '/' . $this->getLogicalId() . "/" . array_shift($files);
        }
    }

}
include_file('core', 'Ftpd', 'class', 'FtpdCmd');