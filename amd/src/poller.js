// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript behaviour for mod_crossduel.
 *
 * @module     mod_crossduel/poller
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



ddefine(['jquery', 'core/ajax'], function($, Ajax) {

    return {
        init: function(cmid, initialState) {

            let lastGameId = initialState && initialState.gameid ? initialState.gameid : 0;
            let lastStatus = initialState && initialState.status ? initialState.status : '';
            let lastModified = initialState && initialState.timemodified ? initialState.timemodified : 0;
            let lastMoveTime = initialState && initialState.lastmovetime ? initialState.lastmovetime : 0;
            let polling = false;

            function hasChanged(response) {
                if (!response.hasgame) {
                    return false;
                }

                return response.gameid !== lastGameId ||
                    response.status !== lastStatus ||
                    response.timemodified > lastModified ||
                    response.lastmovetime > lastMoveTime;
            }

            function poll() {
                if (polling) {
                    return;
                }

                polling = true;

                Ajax.call([{
                    methodname: 'mod_crossduel_get_game_state',
                    args: { cmid: cmid }
                }])[0]
                .done(function(response) {
                    if (hasChanged(response)) {
                        window.location.reload();
                    }
                })
                .fail(function(error) {
                    console.error('CrossDuel polling error', error);
                })
                .always(function() {
                    polling = false;
                });
            }

            setInterval(poll, 3000);
        }
    };
});