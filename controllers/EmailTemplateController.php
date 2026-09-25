<?php
class EmailTemplateController extends Controller {

    public function index(): void {
        Auth::requireRole('admin');

        $tpl = $this->db->query('SELECT template_html, subject FROM email_template LIMIT 1')->fetch();
        $this->render('email-template/index', [
            'pageTitle'    => 'Πρότυπο Email',
            'subject'      => $tpl['subject']      ?? '',
            'templateHtml' => $tpl['template_html'] ?? '',
        ]);
    }

    public function save(): void {
        Auth::requireRole('admin');
        $this->verifyCsrf();

        $subject      = trim($_POST['subject']       ?? '');
        $templateHtml = $_POST['template_html']      ?? '';

        $exists = $this->db->query('SELECT COUNT(*) FROM email_template')->fetchColumn();
        if ($exists) {
            $this->db->prepare('UPDATE email_template SET subject=?, template_html=? WHERE id=1')
                     ->execute([$subject, $templateHtml]);
        } else {
            $this->db->prepare('INSERT INTO email_template (subject, template_html) VALUES (?,?)')
                     ->execute([$subject, $templateHtml]);
        }

        $this->redirect('/administration/email-template?saved=1');
    }
}
