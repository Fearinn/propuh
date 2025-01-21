/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * propuh implementation : © Matheus Gomes matheusgomesforwork@gmail.com
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * propuh.js
 *
 * propuh user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
  "dojo",
  "dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
  `${g_gamethemeurl}modules/js/bga-zoom.js`,
  `${g_gamethemeurl}modules/js/bga-cards.js`,
], function (dojo, declare) {
  return declare("bgagame.propuh", ebg.core.gamegui, {
    constructor: function () {
      console.log("propuh constructor");
    },

    setup: function (gamedatas) {
      console.log("Starting game setup");

      this.pph = {
        managers: {},
        info: {},
        globals: {},
        stocks: {
          trick: {},
        },
      };

      this.pph.managers.zoom = new ZoomManager({
        element: document.getElementById("pph_gameArea"),
        localStorageZoomKey: "propuh-zoom",
        zoomControls: {
          color: "black",
        },
        zoomLevels: [0.5, 0.75, 1, 1.25, 1.5],
        smooth: true,
        onZoomChange: (zoom) => {},
      });

      this.pph.managers.trick = new CardManager(this, {
        cardHeight: 209.6,
        cardWidth: 150,
        selectedCardClass: "pph_card-selected",
        getId: (card) => `trick-${card.id}`,
        setupDiv: (card, element) => {
          element.classList.add("pph_trickCard");
        },
        setupFrontDiv: (card, element) => {
          card.type_arg = Number(card.type_arg);
          const backgroundPosition = `${card.type_arg * 100}% 0`;
          element.parentElement.parentElement.style.backgroundPosition =
            backgroundPosition;
        },
        setupBackDiv: (card, element) => {},
      });

      this.pph.stocks.deck = new Deck(
        this.pph.managers.trick,
        document.getElementById("pph_deck"),
        {
          cardNumber: 28,
          counter: {
            position: "center",
            extraClasses: "text-shadow pph_deckCounter",
          },
        }
      );

      this.pph.stocks.trick.hand = new HandStock(
        this.pph.managers.trick,
        document.getElementById("pph_hand"),
        {
          sort: (a, b) => {
            return Number(b.type_arg) - Number(a.type_arg);
          },
        }
      );

      this.pph.stocks.trick.hand.onSelectionChange = (
        selection,
        lastChange
      ) => {
        this.statusBar.removeActionButtons();

        if (selection.length === 1) {
          this.statusBar.addActionButton(_("Confirm card"), () => {
            this.onConfirmCard(lastChange);
          });
          return;
        }
      };

      this.pph.stocks.trick.hand.addCards(
        gamedatas.hand,
        {},
        { visible: true }
      );

      for (const player_id in gamedatas.players) {
        const playerPanel = this.getPlayerPanelElement(player_id);
        playerPanel.classList.add("pph_playerPanel");
        const playerRole = gamedatas.players[player_id].role;

        playerPanel.insertAdjacentHTML(
          "beforeend",
          `<div id="pph_panelToken-${player_id}" class="pph_token" data-role=${playerRole}></div>`
        );

        if (player_id == this.player_id) {
          continue;
        }

        playerPanel.insertAdjacentHTML(
          "beforeend",
          `<div id="pph_voidHand-${player_id}" class="pph_voidHand"></div>`
        );
        this.pph.stocks.trick.void = new VoidStock(
          this.pph.managers.trick,
          document.getElementById(`pph_voidHand-${player_id}`),
          {}
        );
      }
      // Setup game notifications to handle (see "setupNotifications" method below)
      this.setupNotifications();

      console.log("Ending game setup");
    },

    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName, args);

      if (stateName === "playerTurn") {
        this.pph.stocks.trick.hand.setSelectionMode("single");
      }

      if (stateName === "client_pickLocation") {
        this.statusBar.setTitle("${you} must select a location");
        this.statusBar.addActionButton(_("Select other card"), () => {
          this.restoreServerGameState();
        }, {
          color: "secondary",
        });

        const card = args.client_args.card;

        this.pph.stocks.trick.hand
          .getCardElement(card)
          .classList.add("pph_card-selected");

        const boardElements = document.querySelectorAll("[data-board]");
        boardElements.forEach((boardElement) => {
          boardElement.classList.add("pph_board-selectable");
          boardElement.onclick = () => {
            boardElement.classList.toggle("pph_board-selected");

            boardElements.forEach((element) => {
              if (element.id !== boardElement.id) {
                element.classList.remove("pph_board-selected");
              }
            });
          };
        });
      }
    },

    // onLeavingState: this method is called each time we are leaving a game state.
    //                 You can use this method to perform some user interface changes at this moment.
    //
    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      if (stateName === "playerTurn") {
        this.pph.stocks.trick.hand.setSelectionMode("none");
      }
    },

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    onUpdateActionButtons: function (stateName, args) {
      console.log("onUpdateActionButtons: " + stateName, args);
    },

    ///////////////////////////////////////////////////
    //// Utility methods

    onConfirmCard: function (card) {
      this.setClientState("client_pickLocation", {
        client_args: {
          card,
        },
      });
    },

    ///////////////////////////////////////////////////
    //// Player's action

    performAction: function (action, args = {}) {
      this.bgaPerformAction(action, args);
    },

    actPlayCard: function (card, location) {
      this.performAction("actPlayCard", { card, location });
    },

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications
    setupNotifications: function () {
      console.log("notifications subscriptions setup");
    },
  });
});
