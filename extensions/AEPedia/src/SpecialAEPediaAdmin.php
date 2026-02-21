<?php

namespace MediaWiki\Extension\AEPedia;

use SpecialPage;

class SpecialAEPediaAdmin extends SpecialPage {

    private AllowlistManager $allowlistManager;
    private GroupManager $groupManager;

    public function __construct(
        AllowlistManager $allowlistManager,
        GroupManager $groupManager
    ) {
        parent::__construct( 'AEPediaAdmin', 'aepedia-admin' );
        $this->allowlistManager = $allowlistManager;
        $this->groupManager     = $groupManager;
    }

    public function getDescription(): \Message {
        return $this->msg( 'aepedia-admin-title' );
    }

    public function execute( $subPage ): void {
        $this->checkPermissions();
        $out = $this->getOutput();
        $out->setPageTitle( $this->msg( 'aepedia-admin-title' )->text() );
        $out->addModuleStyles( 'mediawiki.special' );

        $tab = $this->getRequest()->getVal( 'tab', 'allowlist' );

        $tabs = [
            'allowlist' => $this->msg( 'aepedia-tab-allowlist' )->text(),
            'groups'    => $this->msg( 'aepedia-tab-groups' )->text(),
        ];
        $tabHtml = '<div class="aepedia-tabs">';
        foreach ( $tabs as $key => $label ) {
            $url   = $this->getPageTitle()->getLocalURL( [ 'tab' => $key ] );
            $class = $key === $tab ? ' class="active"' : '';
            $tabHtml .= "<a href=\"{$url}\"{$class}>"
                . htmlspecialchars( $label ) . "</a>";
        }
        $tabHtml .= '</div>';
        $out->addHTML( $tabHtml );

        $out->addModules( 'ext.aepedia.admin' );
        $out->addJsConfigVars( [
            'aepedia.columnNumberedLabel'    => $this->msg( 'aepedia-csv-column-numbered' )->plain(),
            'aepedia.noEmailsError'          => $this->msg( 'aepedia-allowlist-error-empty' )->plain(),
            'aepedia.confirmAllowlist'       => $this->msg( 'aepedia-allowlist-confirm' )->plain(),
            'aepedia.confirmGroups'          => $this->msg( 'aepedia-groups-confirm' )->plain(),
            'aepedia.filterValuePlaceholder' => $this->msg( 'aepedia-csv-filter-value-placeholder' )->plain(),
        ] );

        if ( $tab === 'allowlist' ) {
            $this->handleAllowlistTab();
        } else {
            $this->handleGroupsTab();
        }
    }

    // -------------------------------------------------------------------------
    // ALLOWLIST TAB
    // -------------------------------------------------------------------------

    private function handleAllowlistTab(): void {
        $request = $this->getRequest();
        $out     = $this->getOutput();

        if ( $request->wasPosted() && $request->getVal( 'action' ) === 'import-allowlist' ) {
            $this->checkToken();
            $emails = $this->parseEmailsList( $request->getVal( 'emails', '' ) );

            if ( empty( $emails ) ) {
                $out->addHTML( $this->errorBox(
                    $this->msg( 'aepedia-allowlist-error-empty' )->escaped()
                ) );
            } else {
                $result = $this->allowlistManager->replaceAllowlist( $emails );
                $out->addHTML( $this->successBox(
                    $this->msg( 'aepedia-allowlist-success',
                        $result['added'],
                        $result['removed'],
                        $result['blocked'],
                        $result['unblocked']
                    )->escaped()
                ) );
            }
        }

        $out->addHTML( '<h2>' . $this->msg( 'aepedia-tab-allowlist' )->escaped() . '</h2>' );
        $this->renderCsvForm(
            'allowlist',
            'import-allowlist',
            $this->msg( 'aepedia-allowlist-intro' )->parse(),
            $this->msg( 'aepedia-allowlist-submit' )->escaped(),
        );

        $rows  = $this->allowlistManager->getAllowlistEmails();
        $count = count( $rows );
        $this->renderEmailTable(
            $this->msg( 'aepedia-allowlist-current', $count )->escaped(),
            $this->msg( 'aepedia-allowlist-col-email' )->escaped(),
            $rows,
            'allowlist',
        );
    }

    // -------------------------------------------------------------------------
    // GROUPS TAB
    // -------------------------------------------------------------------------

    private function handleGroupsTab(): void {
        $request = $this->getRequest();
        $out     = $this->getOutput();

        if ( $request->wasPosted() && $request->getVal( 'action' ) === 'import-group' ) {
            $this->checkToken();
            $group  = $request->getVal( 'group', '' );
            $emails = $this->parseEmailsList( $request->getVal( 'emails', '' ) );

            if ( !in_array( $group, GroupManager::MANAGED_GROUPS, true ) ) {
                $out->addHTML( $this->errorBox(
                    $this->msg( 'aepedia-groups-error-invalid' )->escaped()
                ) );
            } else {
                $result = $this->groupManager->replaceGroupMembers( $group, $emails );
                $out->addHTML( $this->successBox(
                    $this->msg( 'aepedia-groups-success',
                        $group,
                        $result['added'],
                        $result['removed']
                    )->escaped()
                ) );
            }
        }

        $lGroup       = $this->msg( 'aepedia-groups-label-group' )->escaped();
        $groupOptions = '';
        foreach ( GroupManager::MANAGED_GROUPS as $g ) {
            $groupOptions .= '<option value="' . htmlspecialchars( $g ) . '">'
                . htmlspecialchars( $g ) . '</option>';
        }
        $extraFields = "
            <p>
                <label><strong>{$lGroup}</strong></label><br>
                <select name=\"group\">{$groupOptions}</select>
            </p>";

        $out->addHTML( '<h2>' . $this->msg( 'aepedia-tab-groups' )->escaped() . '</h2>' );
        $this->renderCsvForm(
            'groups',
            'import-group',
            $this->msg( 'aepedia-groups-intro' )->parse(),
            $this->msg( 'aepedia-groups-submit' )->escaped(),
            $extraFields,
        );

        $colEmail = $this->msg( 'aepedia-groups-col-email' )->escaped();
        foreach ( GroupManager::MANAGED_GROUPS as $group ) {
            $members = $this->groupManager->getGroupMembers( $group );
            $count   = count( $members );
            $title   = $this->msg( 'aepedia-groups-members-title', $group, $count )->escaped();

            if ( $count === 0 ) {
                $out->addHTML( "<h3>{$title}</h3>" );
                $out->addHTML( '<p><em>' . $this->msg( 'aepedia-groups-no-members' )->escaped() . '</em></p>' );
                continue;
            }
            $this->renderEmailTable( $title, $colEmail, $members, $group );
        }
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /**
     * Render a CSV import form with file input, column selector, filters, and submit.
     *
     * @param string $tab          Tab key (used as form ID suffix and hidden field)
     * @param string $action       Hidden action value
     * @param string $intro        Intro HTML paragraph
     * @param string $submitLabel  Submit button label
     * @param string $extraFields  Extra HTML inserted before the file input
     */
    private function renderCsvForm(
        string $tab,
        string $action,
        string $intro,
        string $submitLabel,
        string $extraFields = '',
    ): void {
        $out    = $this->getOutput();
        $token  = htmlspecialchars(
            $this->getContext()->getCsrfTokenSet()->getToken()->toString()
        );
        $lFile   = $this->msg( 'aepedia-csv-file-label' )->escaped();
        $lCol    = $this->msg( 'aepedia-csv-columns-label' )->escaped();
        $lHint   = $this->msg( 'aepedia-csv-columns-hint' )->escaped();
        $lFilter = $this->msg( 'aepedia-csv-filter-label' )->escaped();
        $lFHint  = $this->msg( 'aepedia-csv-filter-hint' )->escaped();
        $lFAdd   = $this->msg( 'aepedia-csv-filter-add' )->escaped();
        $col1    = $this->msg( 'aepedia-csv-column-numbered', 1 )->escaped();

        $out->addHTML( "
            <p>{$intro}</p>
            <form id=\"aepedia-form-{$tab}\" class=\"aepedia-form\" method=\"post\">
                <input type=\"hidden\" name=\"tab\" value=\"{$tab}\">
                <input type=\"hidden\" name=\"action\" value=\"{$action}\">
                <input type=\"hidden\" name=\"wpEditToken\" value=\"{$token}\">
                <textarea name=\"emails\"></textarea>
                <p>
                    <label><strong>{$lFile}</strong></label><br>
                    <input type=\"file\" name=\"csv_file\" accept=\".csv,.txt\">
                </p>
                <div class=\"aepedia-csv-options\">
                    {$extraFields}
                    <p>
                        <label><strong>{$lCol}</strong></label><br>
                        <small>{$lHint}</small><br>
                        <select name=\"csv_cols\" class=\"aepedia-col-select\" multiple>
                            <option value=\"0\" selected>{$col1}</option>
                        </select>
                    </p>
                    <div class=\"aepedia-filters\">
                        <label><strong>{$lFilter}</strong></label><br>
                        <small>{$lFHint}</small><br>
                        <div class=\"aepedia-filter-list\"></div>
                        <button type=\"button\" class=\"mw-ui-button aepedia-filter-add\">{$lFAdd}</button>
                    </div>
                    <button type=\"submit\" class=\"mw-ui-button mw-ui-progressive\">
                        {$submitLabel}
                    </button>
                </div>
            </form>
        " );
    }

    /**
     * Render an email list as a sortable table with a CSV download button.
     *
     * @param string   $title    Section heading (already escaped)
     * @param string   $colName  Column header label (already escaped)
     * @param string[] $emails   List of email addresses
     * @param string   $filename Download filename (without extension)
     */
    private function renderEmailTable( string $title, string $colName, array $emails, string $filename = 'emails' ): void {
        $out = $this->getOutput();
        $dlLabel = $this->msg( 'aepedia-csv-download' )->escaped();
        $out->addHTML( "
            <h3>{$title} <a href=\"#\" class=\"aepedia-csv-download\"
                data-filename=\"" . htmlspecialchars( $filename ) . "\">{$dlLabel}</a></h3>" );
        $out->addHTML( "<table class=\"wikitable sortable aepedia-table\"><thead><tr><th>{$colName}</th></tr></thead><tbody>" );
        foreach ( $emails as $email ) {
            $out->addHTML( '<tr><td>' . htmlspecialchars( $email ) . '</td></tr>' );
        }
        $out->addHTML( '</tbody></table>' );
    }

    /**
     * Parse the newline-separated email list submitted by the JS client.
     * Validates each entry server-side as a safety net.
     *
     * @return string[]
     */
    private function parseEmailsList( string $raw ): array {
        $emails = [];
        foreach ( explode( "\n", $raw ) as $line ) {
            $email = strtolower( trim( $line ) );
            if ( $email !== '' && filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                $emails[] = $email;
            }
        }
        return array_unique( $emails );
    }

    private function checkToken(): void {
        if ( !$this->getContext()->getCsrfTokenSet()->matchTokenField( 'wpEditToken' ) ) {
            $this->getOutput()->showErrorPage( 'sessionfailure-title', 'sessionfailure' );
            exit;
        }
    }

    private function successBox( string $msg ): string {
        return '<div class="successbox aepedia-flash">' . $msg . '</div>';
    }

    private function errorBox( string $msg ): string {
        return '<div class="errorbox aepedia-flash">' . $msg . '</div>';
    }

    protected function getGroupName(): string {
        return 'users';
    }
}