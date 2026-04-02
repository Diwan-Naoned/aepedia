<?php

namespace MediaWiki\Extension\AEPedia;

use MediaWiki\MediaWikiServices;
use SpecialPage;

class SpecialAEPediaAdmin extends SpecialPage {

    private GroupManager $groupManager;

    public function __construct( GroupManager $groupManager ) {
        parent::__construct( 'AEPediaAdmin', 'aepedia-admin' );
        $this->groupManager = $groupManager;
    }

    public function getDescription(): \Message {
        return $this->msg( 'aepedia-admin-title' );
    }

    public function execute( $subPage ): void {
        $this->checkPermissions();
        $out = $this->getOutput();
        $out->setPageTitle( $this->msg( 'aepedia-admin-title' )->text() );

        $out->addModules( 'ext.aepedia.admin' );
        $out->addJsConfigVars( [
            'aepedia.columnNumberedLabel'    => $this->msg( 'aepedia-csv-column-numbered' )->plain(),
            'aepedia.noEmailsError'          => $this->msg( 'aepedia-groups-error-empty' )->plain(),
            'aepedia.confirmGroups'          => $this->msg( 'aepedia-groups-confirm' )->plain(),
            'aepedia.filterValuePlaceholder' => $this->msg( 'aepedia-csv-filter-value-placeholder' )->plain(),
            'aepedia.fileNoneLabel'          => $this->msg( 'aepedia-csv-file-none' )->plain(),
        ] );

        $this->handleGroupsPage();
    }

    // -------------------------------------------------------------------------
    // GROUPS PAGE
    // -------------------------------------------------------------------------

    private function handleGroupsPage(): void {
        $out = $this->getOutput();

        $visibleGroups = $this->getVisibleGroups();

        if ( $this->getRequest()->wasPosted() && $this->getRequest()->getVal( 'action' ) === 'import-group' ) {
            $this->handleGroupsPost( $visibleGroups );
        }

        $out->addHTML( '<h2>' . $this->msg( 'aepedia-tab-form-title' )->escaped() . '</h2>' );
        $this->renderForm( $visibleGroups );

        $out->addHTML( '<h2>' . $this->msg( 'aepedia-tab-assignations-title' )->escaped() . '</h2>' );
        $this->renderCurrentAssignations($visibleGroups);

    }

    /**
     * Handle the POST request for the groups import form.
     *
     * @param string[] $visibleGroups List of group names the current user may manage
     */
    private function handleGroupsPost( array $visibleGroups ): void {
        $this->checkToken();
        $request = $this->getRequest();
        $out     = $this->getOutput();
        $group   = $request->getVal( 'group', '' );
        $emails  = $this->parseEmailsList( $request->getVal( 'emails', '' ) );

        if ( !in_array( $group, $visibleGroups, true ) ) {
            $out->addHTML( $this->errorBox(
                $this->msg( 'aepedia-groups-error-invalid' )->escaped()
            ) );
        } elseif ( empty( $emails ) ) {
            $out->addHTML( $this->errorBox(
                $this->msg( 'aepedia-groups-error-empty' )->escaped()
            ) );
        } else {
            $result = $this->groupManager->replaceGroupMembers( $group, $emails );
            $out->addHTML( $this->successBox(
                $this->msg( 'aepedia-groups-success',
                    $this->getGroupDisplayName( $group ),
                    $result['added'],
                    $result['removed']
                )
            ) );
        }
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /**
     * Render the CSV import form for the groups tab.
     *
     * @param string[] $visibleGroups List of group names the current user may manage
     */
    private function renderForm( array $visibleGroups ): void {
        $out = $this->getOutput();

        $lGroup       = $this->msg( 'aepedia-groups-label-group' )->escaped();
        $groupOptions = '';
        foreach ( $visibleGroups as $g ) {
            $label        = $this->getGroupDisplayName( $g );
            $groupOptions .= '<option value="' . htmlspecialchars( $g ) . '">'
                . htmlspecialchars( $label ) . '</option>';
        }
        $groupSelectField = "
            <p>
                <label><strong>{$lGroup}</strong></label><br>
                <select name=\"group\" class=\"cdx-select\">{$groupOptions}</select>
            </p>";

        $token     = htmlspecialchars( $this->getContext()->getCsrfTokenSet()->getToken()->toString() );
        $lFile     = $this->msg( 'aepedia-csv-file-label' )->escaped();
        $lFileBtn  = $this->msg( 'aepedia-csv-file-button' )->escaped();
        $lFileNone = $this->msg( 'aepedia-csv-file-none' )->escaped();
        $lCol      = $this->msg( 'aepedia-csv-columns-label' )->escaped();
        $lHint     = $this->msg( 'aepedia-csv-columns-hint' )->escaped();
        $lFilter   = $this->msg( 'aepedia-csv-filter-label' )->escaped();
        $lFHint    = $this->msg( 'aepedia-csv-filter-hint' )->escaped();
        $lFAdd     = $this->msg( 'aepedia-csv-filter-add' )->escaped();
        $col1      = $this->msg( 'aepedia-csv-column-numbered', 1 )->escaped();
        $intro     = $this->msg( 'aepedia-groups-intro' )->parse();
        $submit    = $this->msg( 'aepedia-groups-submit' )->escaped();

        $out->addHTML( "
            <p>{$intro}</p>
            <form id=\"aepedia-form\" class=\"aepedia-form\" method=\"post\">
                <input type=\"hidden\" name=\"action\" value=\"import-group\">
                <input type=\"hidden\" name=\"wpEditToken\" value=\"{$token}\">
                <textarea name=\"emails\"></textarea>
                <div class=\"aepedia-csv-required\">
                    {$groupSelectField}
                    <p>
                        <label><strong>{$lFile}</strong></label><br>
                        <input type=\"file\" name=\"csv_file\" accept=\".csv,.txt\" class=\"aepedia-file-input\">
                        <span class=\"aepedia-file-trigger\">
                            <button type=\"button\" class=\"cdx-button\">{$lFileBtn}</button>
                            <span class=\"aepedia-file-name\">{$lFileNone}</span>
                        </span>
                    </p>
                </div>
                <div class=\"aepedia-csv-options\">
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
                        <button type=\"button\" class=\"cdx-button aepedia-filter-add\">{$lFAdd}</button>
                    </div>
                    <button type=\"submit\" class=\"cdx-button cdx-button--action-progressive cdx-button--weight-primary\">
                        {$submit}
                    </button>
                </div>
            </form>
        " );
    }

    private function renderCurrentAssignations(array $visibleGroups): void {
        $out = $this->getOutput();

        foreach ( $visibleGroups as $group ) {
            $members      = $this->groupManager->getGroupMembers( $group );
            $count        = count( $members );
            $displayName  = $this->getGroupDisplayName($group);
            $title        = $this->msg( 'aepedia-groups-members-title', $displayName, $count )->escaped();

            $out->addHTML('<details>');
            if ( $count === 0 ) {
                $out->addHTML( "<summary><h3>{$title}</h3></summary>" );
                $out->addHTML( '<p><em>' . $this->msg( 'aepedia-groups-no-members' )->escaped() . '</em></p>' );
                continue;
            }
            $this->renderEmailTable( $title, $members, $group );
            $out->addHTML('</details>');
        }
    }

    /**
     * Returns a localised label for a school group
     *
     * @params string $groupName name of the group to display
     * @return string
     */
    private function getGroupDisplayName(string $groupName): string {
        $lang = $this->getLanguage()->getCode();
        return GroupManager::GROUP_LABELS[$groupName][$lang]
                ?? GroupManager::GROUP_LABELS[$groupName]['fr']
                ?? $groupName;
    }

    /**
     * Return the list of groups the current user is allowed to manage.
     *
     * - sysop: all MANAGED_GROUPS
     * - admin_skol (non-sysop): only the skol_* groups they personally belong to
     *
     * @return string[]
     */
    private function getVisibleGroups(): array {
        $user       = $this->getUser();
        $ugManager  = MediaWikiServices::getInstance()->getUserGroupManager();
        $userGroups = $ugManager->getUserGroups( $user );

        if ( in_array( 'sysop', $userGroups, true ) ) {
            return GroupManager::MANAGED_GROUPS;
        }

        // admin_skol path — intersect their MW groups with MANAGED_GROUPS
        return array_values( array_intersect( GroupManager::MANAGED_GROUPS, $userGroups ) );
    }

    /**
     * Render an email list as a sortable table with a CSV download button.
     *
     * @param string   $title    Section heading (already escaped)
     * @param string   $colName  Column header label (already escaped)
     * @param string[] $emails   List of email addresses
     * @param string   $filename Download filename (without extension)
     */
    private function renderEmailTable( string $title, array $emails, string $filename = 'emails' ): void {
        $colName = $this->msg( 'aepedia-groups-col-email' )->escaped();
        $out = $this->getOutput();
        $dlLabel = $this->msg( 'aepedia-csv-download' )->escaped();
        $out->addHTML( "
            <summary>
                <h3>{$title} <a href=\"#\" class=\"aepedia-csv-download\"
                    data-filename=\"" . htmlspecialchars( $filename ) . "\">{$dlLabel}</a></h3>
            </summary>" );
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
        return '<div class="cdx-message cdx-message--block cdx-message--success aepedia-flash" role="status">'
            . '<span class="cdx-message__icon"></span>'
            . '<div class="cdx-message__content">' . $msg . '</div>'
            . '</div>';
    }

    private function errorBox( string $msg ): string {
        return '<div class="cdx-message cdx-message--block cdx-message--error aepedia-flash" role="alert">'
            . '<span class="cdx-message__icon"></span>'
            . '<div class="cdx-message__content">' . $msg . '</div>'
            . '</div>';
    }

    protected function getGroupName(): string {
        return 'users';
    }
}
