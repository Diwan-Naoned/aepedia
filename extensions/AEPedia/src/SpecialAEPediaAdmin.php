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
        $tabHtml = '<div class="mw-tabs" style="margin-bottom:1em;">';
        foreach ( $tabs as $key => $label ) {
            $url   = $this->getPageTitle()->getLocalURL( [ 'tab' => $key ] );
            $style = $key === $tab
                ? 'font-weight:bold;border-bottom:2px solid #3366cc;padding-bottom:4px;'
                : '';
            $tabHtml .= "<a href=\"{$url}\" style=\"margin-right:1.5em;{$style}\">"
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
            'aepedia.filterLabel'            => $this->msg( 'aepedia-csv-filter-label' )->plain(),
            'aepedia.filterHint'             => $this->msg( 'aepedia-csv-filter-hint' )->plain(),
            'aepedia.filterAdd'              => $this->msg( 'aepedia-csv-filter-add' )->plain(),
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

        $token  = $this->getContext()->getCsrfTokenSet()->getToken()->toString();
        $intro  = $this->msg( 'aepedia-allowlist-intro' )->parse();
        $lFile  = $this->msg( 'aepedia-csv-file-label' )->escaped();
        $lCol   = $this->msg( 'aepedia-csv-columns-label' )->escaped();
        $lHint  = $this->msg( 'aepedia-csv-columns-hint' )->escaped();
        $submit = $this->msg( 'aepedia-allowlist-submit' )->escaped();

        $out->addHTML( '<h2>' . $this->msg( 'aepedia-tab-allowlist' )->escaped() . '</h2>' );
        $out->addHTML( "
            <p>{$intro}</p>
            <form id=\"aepedia-form-allowlist\" method=\"post\">
                <input type=\"hidden\" name=\"tab\" value=\"allowlist\">
                <input type=\"hidden\" name=\"action\" value=\"import-allowlist\">
                <input type=\"hidden\" name=\"wpEditToken\" value=\"" . htmlspecialchars( $token ) . "\">
                <textarea name=\"emails\" style=\"display:none;\"></textarea>
                <p>
                    <label><strong>{$lFile}</strong></label><br>
                    <input type=\"file\" name=\"csv_file\" accept=\".csv,.txt\">
                </p>
                <p>
                    <label><strong>{$lCol}</strong></label><br>
                    <small>{$lHint}</small><br>
                    <select name=\"csv_cols\" class=\"aepedia-col-select\" multiple style=\"min-width:250px;margin-top:0.3em;\">
                        <option value=\"0\" selected>" . $this->msg( 'aepedia-csv-column-numbered', 1 )->escaped() . "</option>
                    </select>
                </p>
                <div class=\"aepedia-filters\"></div>
                <button type=\"submit\" class=\"mw-ui-button mw-ui-progressive\" style=\"margin-top:0.5em;\">
                    {$submit}
                </button>
            </form>
        " );

        $rows  = $this->allowlistManager->getAllowlistEmails();
        $count = count( $rows );
        $title = $this->msg( 'aepedia-allowlist-current', $count )->escaped();
        $col   = $this->msg( 'aepedia-allowlist-col-email' )->escaped();

        $out->addHTML( "<h3>{$title}</h3>" );
        $out->addHTML( "<table class=\"wikitable sortable\" style=\"width:100%\"><thead><tr><th>{$col}</th></tr></thead><tbody>" );
        foreach ( $rows as $email ) {
            $out->addHTML( '<tr><td>' . htmlspecialchars( $email ) . '</td></tr>' );
        }
        $out->addHTML( '</tbody></table>' );
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

        $token        = $this->getContext()->getCsrfTokenSet()->getToken()->toString();
        $intro        = $this->msg( 'aepedia-groups-intro' )->parse();
        $lGroup       = $this->msg( 'aepedia-groups-label-group' )->escaped();
        $lFile        = $this->msg( 'aepedia-csv-file-label' )->escaped();
        $lCol         = $this->msg( 'aepedia-csv-columns-label' )->escaped();
        $lHint        = $this->msg( 'aepedia-csv-columns-hint' )->escaped();
        $submit       = $this->msg( 'aepedia-groups-submit' )->escaped();

        $groupOptions = '';
        foreach ( GroupManager::MANAGED_GROUPS as $g ) {
            $groupOptions .= '<option value="' . htmlspecialchars( $g ) . '">'
                . htmlspecialchars( $g ) . '</option>';
        }

        $out->addHTML( '<h2>' . $this->msg( 'aepedia-tab-groups' )->escaped() . '</h2>' );
        $out->addHTML( "
            <p>{$intro}</p>
            <form id=\"aepedia-form-groups\" method=\"post\">
                <input type=\"hidden\" name=\"tab\" value=\"groups\">
                <input type=\"hidden\" name=\"action\" value=\"import-group\">
                <input type=\"hidden\" name=\"wpEditToken\" value=\"" . htmlspecialchars( $token ) . "\">
                <textarea name=\"emails\" style=\"display:none;\"></textarea>
                <p>
                    <label><strong>{$lGroup}</strong></label>
                    <select name=\"group\">{$groupOptions}</select>
                </p>
                <p>
                    <label><strong>{$lFile}</strong></label><br>
                    <input type=\"file\" name=\"csv_file\" accept=\".csv,.txt\">
                </p>
                <p>
                    <label><strong>{$lCol}</strong></label><br>
                    <small>{$lHint}</small><br>
                    <select name=\"csv_cols\" class=\"aepedia-col-select\" multiple style=\"min-width:250px;margin-top:0.3em;\">
                        <option value=\"0\" selected>" . $this->msg( 'aepedia-csv-column-numbered', 1 )->escaped() . "</option>
                    </select>
                </p>
                <div class=\"aepedia-filters\"></div>
                <button type=\"submit\" class=\"mw-ui-button mw-ui-progressive\" style=\"margin-top:0.5em;\">
                    {$submit}
                </button>
            </form>
        " );

        $colEmail = $this->msg( 'aepedia-groups-col-email' )->escaped();
        foreach ( GroupManager::MANAGED_GROUPS as $group ) {
            $members = $this->groupManager->getGroupMembers( $group );
            $count   = count( $members );
            $title   = $this->msg( 'aepedia-groups-members-title', $group, $count )->escaped();

            $out->addHTML( "<h3>{$title}</h3>" );
            if ( $count === 0 ) {
                $out->addHTML( '<p><em>' . $this->msg( 'aepedia-groups-no-members' )->escaped() . '</em></p>' );
                continue;
            }
            $out->addHTML( "<table class=\"wikitable sortable\" style=\"width:100%\"><thead><tr><th>{$colEmail}</th></tr></thead><tbody>" );
            foreach ( $members as $email ) {
                $out->addHTML( '<tr><td>' . htmlspecialchars( $email ) . '</td></tr>' );
            }
            $out->addHTML( '</tbody></table>' );
        }
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

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
        return '<div class="successbox" style="margin-bottom:1em;">' . $msg . '</div>';
    }

    private function errorBox( string $msg ): string {
        return '<div class="errorbox" style="margin-bottom:1em;">' . $msg . '</div>';
    }

    protected function getGroupName(): string {
        return 'users';
    }
}