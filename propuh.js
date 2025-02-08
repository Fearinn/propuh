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
  `${g_gamethemeurl}modules/js/bga-help.js`,
], function (dojo, declare) {
  return declare("bgagame.propuh", ebg.core.gamegui, {
    constructor: function () {
      console.log("propuh constructor");
    },

    setup: function (gamedatas) {
      console.log("Starting game setup");

      this.pph = {
        managers: {},
        info: {
          roles: {
            granny: _("Granny"),
            propuh: "Propuh",
          },
          goals: {
            granny: {
              1: _("Set the table"),
              2: _("Cook sarm"),
              4: _("Clean the house"),
              3: _("Make the bed"),
            },
            propuh: {
              1: _("Open the door"),
              3: _("Open the window"),
            },
          },
        },
        stocks: {
          trick: {},
          tokens: {},
          granny: {},
        },
      };

      this.pph.selections = {
        card_id: null,
        location_id: null,
      };

      this.pph.managers.help = new HelpManager(this, {
        buttons: [
          new BgaHelpExpandableButton({
            title: _("Player Aid"),
            expandedWidth: "450px",
            expandedHeight: "315px",
            foldedHtml: `<span class="pph_helpFolded">?</span>`,
            unfoldedHtml: `<div id="pph_aidContainer" class="pph_aidContainer">
              <div class="pph_aidCard" style="background-image: url(${g_gamethemeurl}/img/aid/turnAid.jpg)"></div>
              <div class="pph_aidCard" style="background-image: url(${g_gamethemeurl}/img/aid/${gamedatas.player_role}Aid.jpg)"></div>
            </div>`,
          }),
        ],
      });

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
        selectedCardClass: "pph_trickCard-selected",
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

          const tooltip = this.format_string_recursive(
            _("${role_label} token"),
            {
              role_label: this.pph.info.roles[player_role],
            }
          );
          this.addTooltip(element.id, tooltip, "");
        },
        setupFrontDiv: (card, element) => {},
        setupBackDiv: (card, element) => {},
      });

      this.pph.managers.granny = new CardManager(this, {
        getId: (card) => `granny-${card.id}`,
        setupDiv: (card, element) => {
          element.classList.add("pph_granny");
          this.addTooltip(element.id, _("Granny Standee"), "");
        },
        setupFrontDiv: (card, element) => {},
        setupBackDiv: (card, element) => {},
      });

      // GOALS

      for (const role in this.pph.info.goals) {
        const goals = this.pph.info.goals[role];
        const role_label = this.pph.info.roles[role];
        const goalsElement = document.getElementById(`pph_goals-${role}`);

        document.getElementById(`pph_goalsTitle-${role}`).innerHTML =
          this.format_string_recursive(_("${role_label}'s goals:"), {
            role_label,
          });

        for (const goal_id in goals) {
          const goal = goals[goal_id];
          goalsElement.insertAdjacentHTML(
            "beforeend",
            `<li id=pph_goal-${role}-${goal_id} class="pph_goal">${goal}</li>`
          );

          if (gamedatas.completedGoals[role][goal_id]) {
            document
              .getElementById(`pph_goal-${role}-${goal_id}`)
              .classList.add("pph_completed");
          }
        }
      }

      // HAND 

      const handElement = document.createElement("div");
      handElement.id = "pph_hand";
      handElement.classList.add("pph_hand");

      if (this.getGameUserPreference(100) == 1) {
        document.getElementById("game_play_area").insertAdjacentElement("afterbegin", handElement);
        handElement.classList.add("pph_floatingHand");
      } else {
        document.getElementById("pph_gameArea").insertAdjacentElement("afterbegin", handElement);
      }

      // LOCATIONS

      this.addTooltip("pph_table", _("Table"), "");
      this.addTooltip("pph_locationTable", _("Table"), "");
      this.addTooltip("pph_stove", _("Stove"), "");
      this.addTooltip("pph_locationStove", _("Stove"), "");
      this.addTooltip("pph_bed", _("Bed"), "");
      this.addTooltip("pph_locationBed", _("Bed"), "");

      // CARDS

      this.pph.stocks.trick.deck = new Deck(
        this.pph.managers.trick,
        document.getElementById("pph_deck"),
        {
          cardNumber: gamedatas.deckCount,
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
        this.pph.selections.card_id =
          selection.length > 0 ? lastChange.id : null;
        this.handleConfirmationBtn();
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
        document.getElementById("pph_tableCards"),
        {}
      );

      this.pph.stocks.trick[2] = new HandStock(
        this.pph.managers.trick,
        document.getElementById("pph_stoveCards"),
        {}
      );

      this.pph.stocks.trick[3] = new HandStock(
        this.pph.managers.trick,
        document.getElementById("pph_bedCards"),
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

      this.pph.stocks.tokens[1] = new SlotStock(
        this.pph.managers.tokens,
        document.getElementById("pph_tableTokens"),
        {
          slotsIds: Object.keys(this.pph.info.roles),
          mapCardToSlot: (token) => {
            return token.type;
          },
          slotClasses: ["pph_boardTokens"],
        }
      );

      this.pph.stocks.tokens[2] = new SlotStock(
        this.pph.managers.tokens,
        document.getElementById("pph_stoveTokens"),
        {
          slotsIds: Object.keys(this.pph.info.roles),
          mapCardToSlot: (token) => {
            return token.type;
          },
          slotClasses: ["pph_boardTokens"],
        }
      );

      this.pph.stocks.tokens[3] = new SlotStock(
        this.pph.managers.tokens,
        document.getElementById("pph_bedTokens"),
        {
          slotsIds: Object.keys(this.pph.info.roles),
          mapCardToSlot: (token) => {
            return token.type;
          },
          slotClasses: ["pph_boardTokens"],
        }
      );

      gamedatas.placedTokens.forEach((token) => {
        this.pph.stocks.tokens[token.location].addCard(token);
      });

      // GRANNY STANDEE
      this.pph.stocks.granny[1] = new HandStock(
        this.pph.managers.granny,
        document.getElementById("pph_locationTable"),
        {}
      );

      this.pph.stocks.granny[2] = new HandStock(
        this.pph.managers.granny,
        document.getElementById("pph_locationStove"),
        {}
      );

      this.pph.stocks.granny[3] = new HandStock(
        this.pph.managers.granny,
        document.getElementById("pph_locationBed"),
        {}
      );

      this.pph.stocks.granny[gamedatas.grannyLocation].addCard({
        id: "granny",
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

        this.addTooltip(
          `pph_panelToken-${player_id}`,
          this.pph.info.roles[player_role],
          ""
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
        if (stateName === "grannyMove") {
          this.statusBar.addActionButton(
            _("Skip"),
            () => {
              this.actSkipGrannyMove();
            },
            { color: "alert" }
          );

          const grannyLocation = args.args.grannyLocation;
          this.setBoardsSelectable(grannyLocation);
        }

        if (stateName === "playerTurn") {
          const canUndo = args.args.canUndo;
          if (canUndo) {
            this.statusBar.addActionButton(
              _("Change mind (Standee)"),
              () => {
                this.actUndoSkipGrannyMove();
              },
              { color: "secondary" }
            );
          }

          this.pph.stocks.trick.hand.setSelectionMode("single");
          this.setBoardsSelectable();
        }
      }
    },

    // onLeavingState: this method is called each time we are leaving a game state.
    //                 You can use this method to perform some user interface changes at this moment.
    //
    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      this.pph.selections = {
        card_id: null,
        location_id: null,
      };

      if (stateName === "playerTurn") {
        this.pph.stocks.trick.hand.setSelectionMode("none");
        this.unsetBoardsSelectable();
      }

      if (stateName === "grannyMove") {
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

    getStateName: function () {
      return this.gamedatas.gamestate.name;
    },

    setBoardsSelectable: function (grannyLocation) {
      const selectedClass = "pph_board-selected";
      const boardElements = document.querySelectorAll("[data-board]");

      boardElements.forEach((boardElement) => {
        const location_id = boardElement.dataset.board;
        if (location_id == grannyLocation) {
          boardElement.classList.add("pph_board-unselectable");
          return;
        }

        boardElement.classList.remove("pph_board-unselectable");
        boardElement.classList.add("pph_board-selectable");

        boardElement.onclick = () => {
          boardElement.classList.toggle(selectedClass);

          this.pph.selections.location_id = boardElement.classList.contains(
            selectedClass
          )
            ? location_id
            : null;

          this.handleConfirmationBtn();

          boardElements.forEach((element) => {
            if (element.id !== boardElement.id) {
              element.classList.remove(selectedClass);
            }
          });
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

    handleConfirmationBtn: function () {
      const stateName = this.getStateName();
      const { card_id, location_id } = this.pph.selections;

      const buttonId = "pph_confirmationBtn";
      document.getElementById(buttonId)?.remove();

      if (stateName === "playerTurn") {
        if (card_id && location_id) {
          this.statusBar.addActionButton(
            _("Confirm"),
            () => {
              this.actPlayCard(card_id, location_id);
            },
            {
              id: buttonId,
            }
          );
        }
        return;
      }

      if (location_id) {
        this.statusBar.addActionButton(
          _("Confirm"),
          () => {
            this.actMoveGranny(location_id);
          },
          { id: buttonId }
        );
      }
    },

    ///////////////////////////////////////////////////
    //// Player's action

    performAction: function (action, args = {}) {
      this.bgaPerformAction(action, args);
    },

    actMoveGranny: function (location_id) {
      this.performAction("actMoveGranny", { location_id });
    },

    actSkipGrannyMove: function () {
      this.performAction("actSkipGrannyMove");
    },

    actUndoSkipGrannyMove: function () {
      this.performAction("actUndoSkipGrannyMove");
    },

    actPlayCard: function (card_id, location_id) {
      this.performAction("actPlayCard", { card_id, location_id });
    },

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications
    setupNotifications: function () {
      this.bgaSetupPromiseNotifications({
        minDuration: 1000,
      });
    },

    notif_moveGranny: async function (args) {
      const location_id = args.location_id;
      this.pph.stocks.granny[location_id].addCard({
        id: "granny",
      });
    },

    notif_playCard: async function (args) {
      const player_id = args.player_id;
      const card = args.card;

      this.pph.stocks.trick[card.location].addCard(card, {
        fromElement:
          player_id == this.player_id
            ? document.getElementById(`pph_voidHand-${player_id}`)
            : undefined,
        fromStock: player_id == 1 ? this.pph.stocks.trick.deck : undefined,
      });
    },

    notif_successfulCounterplay: async function (args) {},

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
        fromElement:
          player_id == 1
            ? document.getElementById("pph_discard")
            : document.getElementById(`pph_voidHand-${player_id}`),
      });
    },

    notif_discardToken: async function (args) {
      const token = args.token;
      this.pph.stocks.tokens.discard.addCard(token);
    },

    notif_completeGoal: async function (args) {
      const goal_id = args.goal_id;
      const player_role = args.player_role;
      document
        .getElementById(`pph_goal-${player_role}-${goal_id}`)
        .classList.add("pph_completed");
    },

    notif_incompleteGoal: async function (args) {
      const goal_id = args.goal_id;
      const player_role = args.player_role;
      document
        .getElementById(`pph_goal-${player_role}-${goal_id}`)
        .classList.remove("pph_completed");
    },

    //  @Override

    format_string_recursive(log, args) {
      try {
        if (log && args && !args.processed) {
          args.processed = true;

          const argsKeys = Object.keys(args);
          argsKeys
            .filter((key) => {
              return key.includes("_label");
            })
            .forEach((key) => {
              const label = args[key] === "Propuh" ? "Propuh" : _(args[key]);
              args[key] = `<span style="font-weight: bold">${label}</span>`;
            });

          if (args.value) {
            args.value = `<span style="font-weight: bold">${args.value}</span>`;
          }

          if (args.player_name && args.player_id == 1) {
            args.player_name = `<span class="playername" style="color: red;">${"Propuh"}</span>`;
            args.role_label = "";
            log = log.replace("(${role_label})", "");
          }

          if (args.player_name2 && args.player_id2 == 1) {
            args.player_name2 = `<span class="playername" style="color: red;">${"Propuh"}</span>`;
          }
        }
      } catch (e) {
        console.error(log, args, "Exception thrown", e.stack);
      }

      return this.inherited(arguments);
    },
  });
});
