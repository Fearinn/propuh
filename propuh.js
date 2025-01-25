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
          tokens: {},
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
          const backgroundPosition = `-${card.type_arg * 100}% 0`;
          element.parentElement.parentElement.style.backgroundPosition =
            backgroundPosition;
        },
        setupBackDiv: (card, element) => {},
      });

      this.pph.managers.tokens = new CardManager(this, {
        getId: (card) => `token-${card.id}`,
        setupDiv: (card, element) => {
          element.classList.add("pph_token");
          const player_role = card.type;
          element.dataset.role = player_role;
        },
        setupFrontDiv: (card, element) => {},
        setupBackDiv: (card, element) => {},
      });

      this.pph.stocks.trick.deck = new Deck(
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
            return Number(a.type_arg) - Number(b.type_arg);
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

      this.pph.stocks.trick.discard = new VoidStock(
        this.pph.managers.trick,
        document.getElementById("pph_discard"),
        {}
      );

      this.pph.stocks.trick[1] = new HandStock(
        this.pph.managers.trick,
        document.getElementById("pph_bedCards"),
        {}
      );

      this.pph.stocks.trick[2] = new HandStock(
        this.pph.managers.trick,
        document.getElementById("pph_stoveCards"),
        {}
      );

      this.pph.stocks.trick[3] = new HandStock(
        this.pph.managers.trick,
        document.getElementById("pph_tableCards"),
        {}
      );

      gamedatas.playedCards.forEach((card) => {
        this.pph.stocks.trick[card.location].addCard(card);
      });

      // TOKENS
      this.pph.stocks.tokens.discard = new VoidStock(
        this.pph.managers.tokens,
        document.getElementById("pph_discard"),
        {}
      );

      this.pph.stocks.tokens[1] = new HandStock(
        this.pph.managers.tokens,
        document.getElementById("pph_bedTokens"),
        {
          sort: (a, b) => {
            return a.type - b.type;
          },
          cardOverlap: "30px",
        }
      );

      this.pph.stocks.tokens[2] = new HandStock(
        this.pph.managers.tokens,
        document.getElementById("pph_stoveTokens"),
        {
          sort: (a, b) => {
            return a.type - b.type;
          },
          cardOverlap: "30px",
        }
      );

      this.pph.stocks.tokens[3] = new HandStock(
        this.pph.managers.tokens,
        document.getElementById("pph_tableTokens"),
        {
          sort: (a, b) => {
            return a.type - b.type;
          },
          cardOverlap: "30px",
        }
      );

      gamedatas.placedTokens.forEach((token) => {
        this.pph.stocks.tokens[token.location].addCard(token);
      });

      for (const player_id in gamedatas.players) {
        this.pph.stocks[player_id] = {
          trick: {},
          tokens: {},
        };

        const playerPanel = this.getPlayerPanelElement(player_id);
        playerPanel.classList.add("pph_playerPanel");
        const player_role = gamedatas.players[player_id].role;

        playerPanel.insertAdjacentHTML(
          "beforeend",
          `<div id="pph_panelToken-${player_id}" class="pph_token" data-role=${player_role}></div>`
        );

        playerPanel.insertAdjacentHTML(
          "beforeend",
          `<div id="pph_voidHand-${player_id}" class="pph_voidHand"></div>`
        );

        this.pph.stocks[player_id].trick.void = new VoidStock(
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

      if (this.isCurrentPlayerActive()) {
        if (stateName === "playerTurn") {
          this.pph.stocks.trick.hand.setSelectionMode("single");
        }

        if (stateName === "client_pickLocation") {
          this.statusBar.setTitle("${you} must select a location");
          this.statusBar.addActionButton(
            _("Select other card"),
            () => {
              this.restoreServerGameState();
            },
            {
              color: "secondary",
            }
          );

          const card = args.client_args.card;

          this.pph.stocks.trick.hand
            .getCardElement(card)
            .classList.add("pph_card-selected");

          this.setBoardsSelectable(card);
        }
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

      if (stateName === "client_pickLocation") {
        document
          .querySelector(".pph_card-selected")
          ?.classList.remove("pph_card-selected");
        this.unsetBoardsSelectable();
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

    setBoardsSelectable: function (card) {
      const selectedClass = "pph_board-selected";
      const boardElements = document.querySelectorAll("[data-board]");

      boardElements.forEach((boardElement) => {
        boardElement.classList.add("pph_board-selectable");

        boardElement.onclick = () => {
          const buttonId = "pph_confirmLocation_btn";
          document.getElementById(buttonId)?.remove();

          boardElement.classList.toggle(selectedClass);

          boardElements.forEach((element) => {
            if (element.id !== boardElement.id) {
              element.classList.remove(selectedClass);
            }
          });

          if (boardElement.classList.contains(selectedClass)) {
            this.statusBar.addActionButton(
              _("Confirm location"),
              () => {
                const location_id = boardElement.dataset.board;
                this.actPlayCard(card.id, location_id);
              },
              {
                id: buttonId,
              }
            );
          }
        };
      });
    },

    unsetBoardsSelectable: function () {
      const boardElements = document.querySelectorAll("[data-board]");

      boardElements.forEach((boardElement) => {
        boardElement.classList.remove("pph_board-selectable");
        boardElement.classList.remove("pph_board-selected");
        boardElement.onclick = undefined;
      });
    },

    ///////////////////////////////////////////////////
    //// Player's action

    performAction: function (action, args = {}) {
      this.bgaPerformAction(action, args);
    },

    onConfirmCard: function (card) {
      this.setClientState("client_pickLocation", {
        client_args: {
          card,
        },
      });
    },

    actPlayCard: function (card_id, location_id) {
      this.performAction("actPlayCard", { card_id, location_id });
    },

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications
    setupNotifications: function () {
      this.bgaSetupPromiseNotifications();
    },

    notif_playCard: async function (args) {
      const player_id = args.player_id;
      const card = args.card;

      this.pph.stocks.trick[card.location].addCard(card, {
        fromElement:
          player_id == this.player_id
            ? document.getElementById(`pph_voidHand-${player_id}`)
            : undefined,
      });
    },

    notif_discardCard: async function (args) {
      const card = args.card;
      this.pph.stocks.trick.discard.addCard(card);
    },

    notif_drawCards: async function (args) {
      const cards = args.cards;
      this.pph.stocks.trick.hand.addCards(cards, {
        fromStock: this.pph.stocks.trick.deck,
      });
    },

    notif_newRound: async function (args) {
      const drawnCards = args.cards;

      for (const player_id in drawnCards) {
        if (player_id == this.player_id) {
          continue;
        }

        const cards = drawnCards[player_id];
        this.pph.stocks[player_id].trick.void.addCards(
          cards,
          {
            fromStock: this.pph.stocks.trick.deck,
          },
          { visible: false }
        );
      }
    },

    notif_placeToken: async function (args) {
      const player_id = args.player_id;
      const token = args.token;

      this.pph.stocks.tokens[token.location].addCard(token, {
        fromElement: document.getElementById(`pph_voidHand-${player_id}`),
      });
    },
  });
});
