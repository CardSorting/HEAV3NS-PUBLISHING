/*! @name videojs-contrib-quality-menu @version 1.0.4 @license Apache-2.0 */
(function (QUnit, sinon, videojs) {
	'use strict';

	function _interopDefaultLegacy (e) { return e && typeof e === 'object' && 'default' in e ? e : { 'default': e }; }

	var QUnit__default = /*#__PURE__*/_interopDefaultLegacy(QUnit);
	var sinon__default = /*#__PURE__*/_interopDefaultLegacy(sinon);
	var videojs__default = /*#__PURE__*/_interopDefaultLegacy(videojs);

	var commonjsGlobal = typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : typeof global !== 'undefined' ? global : typeof self !== 'undefined' ? self : {};

	function getAugmentedNamespace(n) {
		if (n.__esModule) return n;
		var a = Object.defineProperty({}, '__esModule', {value: true});
		Object.keys(n).forEach(function (k) {
			var d = Object.getOwnPropertyDescriptor(n, k);
			Object.defineProperty(a, k, d.get ? d : {
				enumerable: true,
				get: function () {
					return n[k];
				}
			});
		});
		return a;
	}

	var _nodeResolve_empty = {};

	var _nodeResolve_empty$1 = /*#__PURE__*/Object.freeze({
		__proto__: null,
		'default': _nodeResolve_empty
	});

	var require$$0 = /*@__PURE__*/getAugmentedNamespace(_nodeResolve_empty$1);

	var topLevel = typeof commonjsGlobal !== 'undefined' ? commonjsGlobal : typeof window !== 'undefined' ? window : {};
	var minDoc = require$$0;
	var doccy;
	if (typeof document !== 'undefined') {
	  doccy = document;
	} else {
	  doccy = topLevel['__GLOBAL_DOCUMENT_CACHE@4'];
	  if (!doccy) {
	    doccy = topLevel['__GLOBAL_DOCUMENT_CACHE@4'] = minDoc;
	  }
	}
	var document_1 = doccy;

	function cov_5brpejo2b() {
	  var path = "/Users/srimron-soutter/repos/github/videojs-contrib-quality-menu/src/quality-menu-item.js";
	  var hash = "51bc091ed3445969dd1592c19f624ce772bc998c";
	  var global = new Function("return this")();
	  var gcv = "__coverage__";
	  var coverageData = {
	    path: "/Users/srimron-soutter/repos/github/videojs-contrib-quality-menu/src/quality-menu-item.js",
	    statementMap: {
	      "0": {
	        start: {
	          line: 6,
	          column: 17
	        },
	        end: {
	          line: 6,
	          column: 49
	        }
	      },
	      "1": {
	        start: {
	          line: 7,
	          column: 12
	        },
	        end: {
	          line: 7,
	          column: 34
	        }
	      },
	      "2": {
	        start: {
	          line: 42,
	          column: 27
	        },
	        end: {
	          line: 42,
	          column: 43
	        }
	      },
	      "3": {
	        start: {
	          line: 49,
	          column: 4
	        },
	        end: {
	          line: 49,
	          column: 38
	        }
	      },
	      "4": {
	        start: {
	          line: 51,
	          column: 4
	        },
	        end: {
	          line: 51,
	          column: 27
	        }
	      },
	      "5": {
	        start: {
	          line: 53,
	          column: 26
	        },
	        end: {
	          line: 53,
	          column: 48
	        }
	      },
	      "6": {
	        start: {
	          line: 55,
	          column: 4
	        },
	        end: {
	          line: 55,
	          column: 34
	        }
	      },
	      "7": {
	        start: {
	          line: 56,
	          column: 4
	        },
	        end: {
	          line: 56,
	          column: 36
	        }
	      },
	      "8": {
	        start: {
	          line: 57,
	          column: 4
	        },
	        end: {
	          line: 57,
	          column: 67
	        }
	      },
	      "9": {
	        start: {
	          line: 59,
	          column: 4
	        },
	        end: {
	          line: 59,
	          column: 42
	        }
	      },
	      "10": {
	        start: {
	          line: 61,
	          column: 4
	        },
	        end: {
	          line: 61,
	          column: 63
	        }
	      },
	      "11": {
	        start: {
	          line: 63,
	          column: 4
	        },
	        end: {
	          line: 65,
	          column: 7
	        }
	      },
	      "12": {
	        start: {
	          line: 64,
	          column: 6
	        },
	        end: {
	          line: 64,
	          column: 66
	        }
	      },
	      "13": {
	        start: {
	          line: 82,
	          column: 15
	        },
	        end: {
	          line: 82,
	          column: 49
	        }
	      },
	      "14": {
	        start: {
	          line: 84,
	          column: 21
	        },
	        end: {
	          line: 87,
	          column: 6
	        }
	      },
	      "15": {
	        start: {
	          line: 89,
	          column: 4
	        },
	        end: {
	          line: 89,
	          column: 30
	        }
	      },
	      "16": {
	        start: {
	          line: 91,
	          column: 4
	        },
	        end: {
	          line: 93,
	          column: 5
	        }
	      },
	      "17": {
	        start: {
	          line: 92,
	          column: 6
	        },
	        end: {
	          line: 92,
	          column: 31
	        }
	      },
	      "18": {
	        start: {
	          line: 95,
	          column: 4
	        },
	        end: {
	          line: 95,
	          column: 14
	        }
	      },
	      "19": {
	        start: {
	          line: 104,
	          column: 4
	        },
	        end: {
	          line: 104,
	          column: 27
	        }
	      },
	      "20": {
	        start: {
	          line: 106,
	          column: 26
	        },
	        end: {
	          line: 106,
	          column: 55
	        }
	      },
	      "21": {
	        start: {
	          line: 107,
	          column: 30
	        },
	        end: {
	          line: 107,
	          column: 57
	        }
	      },
	      "22": {
	        start: {
	          line: 109,
	          column: 4
	        },
	        end: {
	          line: 116,
	          column: 5
	        }
	      },
	      "23": {
	        start: {
	          line: 109,
	          column: 17
	        },
	        end: {
	          line: 109,
	          column: 18
	        }
	      },
	      "24": {
	        start: {
	          line: 109,
	          column: 24
	        },
	        end: {
	          line: 109,
	          column: 44
	        }
	      },
	      "25": {
	        start: {
	          line: 113,
	          column: 6
	        },
	        end: {
	          line: 115,
	          column: 7
	        }
	      },
	      "26": {
	        start: {
	          line: 114,
	          column: 8
	        },
	        end: {
	          line: 114,
	          column: 41
	        }
	      },
	      "27": {
	        start: {
	          line: 118,
	          column: 4
	        },
	        end: {
	          line: 120,
	          column: 5
	        }
	      },
	      "28": {
	        start: {
	          line: 118,
	          column: 17
	        },
	        end: {
	          line: 118,
	          column: 18
	        }
	      },
	      "29": {
	        start: {
	          line: 118,
	          column: 24
	        },
	        end: {
	          line: 118,
	          column: 43
	        }
	      },
	      "30": {
	        start: {
	          line: 119,
	          column: 6
	        },
	        end: {
	          line: 119,
	          column: 52
	        }
	      },
	      "31": {
	        start: {
	          line: 124,
	          column: 4
	        },
	        end: {
	          line: 126,
	          column: 5
	        }
	      },
	      "32": {
	        start: {
	          line: 125,
	          column: 6
	        },
	        end: {
	          line: 125,
	          column: 55
	        }
	      },
	      "33": {
	        start: {
	          line: 135,
	          column: 26
	        },
	        end: {
	          line: 135,
	          column: 55
	        }
	      },
	      "34": {
	        start: {
	          line: 136,
	          column: 19
	        },
	        end: {
	          line: 136,
	          column: 73
	        }
	      },
	      "35": {
	        start: {
	          line: 138,
	          column: 4
	        },
	        end: {
	          line: 138,
	          column: 26
	        }
	      },
	      "36": {
	        start: {
	          line: 149,
	          column: 4
	        },
	        end: {
	          line: 151,
	          column: 5
	        }
	      },
	      "37": {
	        start: {
	          line: 150,
	          column: 6
	        },
	        end: {
	          line: 150,
	          column: 13
	        }
	      },
	      "38": {
	        start: {
	          line: 153,
	          column: 4
	        },
	        end: {
	          line: 179,
	          column: 5
	        }
	      },
	      "39": {
	        start: {
	          line: 154,
	          column: 26
	        },
	        end: {
	          line: 154,
	          column: 48
	        }
	      },
	      "40": {
	        start: {
	          line: 155,
	          column: 6
	        },
	        end: {
	          line: 155,
	          column: 36
	        }
	      },
	      "41": {
	        start: {
	          line: 156,
	          column: 6
	        },
	        end: {
	          line: 156,
	          column: 52
	        }
	      },
	      "42": {
	        start: {
	          line: 159,
	          column: 6
	        },
	        end: {
	          line: 159,
	          column: 78
	        }
	      },
	      "43": {
	        start: {
	          line: 161,
	          column: 25
	        },
	        end: {
	          line: 161,
	          column: 49
	        }
	      },
	      "44": {
	        start: {
	          line: 162,
	          column: 25
	        },
	        end: {
	          line: 162,
	          column: 65
	        }
	      },
	      "45": {
	        start: {
	          line: 164,
	          column: 6
	        },
	        end: {
	          line: 172,
	          column: 7
	        }
	      },
	      "46": {
	        start: {
	          line: 169,
	          column: 8
	        },
	        end: {
	          line: 169,
	          column: 63
	        }
	      },
	      "47": {
	        start: {
	          line: 171,
	          column: 8
	        },
	        end: {
	          line: 171,
	          column: 66
	        }
	      },
	      "48": {
	        start: {
	          line: 174,
	          column: 6
	        },
	        end: {
	          line: 174,
	          column: 39
	        }
	      },
	      "49": {
	        start: {
	          line: 175,
	          column: 6
	        },
	        end: {
	          line: 175,
	          column: 53
	        }
	      },
	      "50": {
	        start: {
	          line: 178,
	          column: 6
	        },
	        end: {
	          line: 178,
	          column: 50
	        }
	      },
	      "51": {
	        start: {
	          line: 188,
	          column: 26
	        },
	        end: {
	          line: 188,
	          column: 55
	        }
	      },
	      "52": {
	        start: {
	          line: 189,
	          column: 23
	        },
	        end: {
	          line: 189,
	          column: 47
	        }
	      },
	      "53": {
	        start: {
	          line: 190,
	          column: 22
	        },
	        end: {
	          line: 190,
	          column: 68
	        }
	      },
	      "54": {
	        start: {
	          line: 192,
	          column: 4
	        },
	        end: {
	          line: 198,
	          column: 5
	        }
	      },
	      "55": {
	        start: {
	          line: 192,
	          column: 17
	        },
	        end: {
	          line: 192,
	          column: 18
	        }
	      },
	      "56": {
	        start: {
	          line: 192,
	          column: 24
	        },
	        end: {
	          line: 192,
	          column: 40
	        }
	      },
	      "57": {
	        start: {
	          line: 193,
	          column: 19
	        },
	        end: {
	          line: 193,
	          column: 31
	        }
	      },
	      "58": {
	        start: {
	          line: 194,
	          column: 21
	        },
	        end: {
	          line: 194,
	          column: 75
	        }
	      },
	      "59": {
	        start: {
	          line: 196,
	          column: 6
	        },
	        end: {
	          line: 196,
	          column: 37
	        }
	      },
	      "60": {
	        start: {
	          line: 197,
	          column: 6
	        },
	        end: {
	          line: 197,
	          column: 28
	        }
	      }
	    },
	    fnMap: {
	      "0": {
	        name: "(anonymous_0)",
	        decl: {
	          start: {
	            line: 41,
	            column: 2
	          },
	          end: {
	            line: 41,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 41,
	            column: 36
	          },
	          end: {
	            line: 66,
	            column: 3
	          }
	        },
	        line: 41
	      },
	      "1": {
	        name: "(anonymous_1)",
	        decl: {
	          start: {
	            line: 63,
	            column: 23
	          },
	          end: {
	            line: 63,
	            column: 24
	          }
	        },
	        loc: {
	          start: {
	            line: 63,
	            column: 29
	          },
	          end: {
	            line: 65,
	            column: 5
	          }
	        },
	        line: 63
	      },
	      "2": {
	        name: "(anonymous_2)",
	        decl: {
	          start: {
	            line: 81,
	            column: 2
	          },
	          end: {
	            line: 81,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 81,
	            column: 31
	          },
	          end: {
	            line: 96,
	            column: 3
	          }
	        },
	        line: 81
	      },
	      "3": {
	        name: "(anonymous_3)",
	        decl: {
	          start: {
	            line: 103,
	            column: 2
	          },
	          end: {
	            line: 103,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 103,
	            column: 16
	          },
	          end: {
	            line: 127,
	            column: 3
	          }
	        },
	        line: 103
	      },
	      "4": {
	        name: "(anonymous_4)",
	        decl: {
	          start: {
	            line: 134,
	            column: 2
	          },
	          end: {
	            line: 134,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 134,
	            column: 24
	          },
	          end: {
	            line: 139,
	            column: 3
	          }
	        },
	        line: 134
	      },
	      "5": {
	        name: "(anonymous_5)",
	        decl: {
	          start: {
	            line: 148,
	            column: 2
	          },
	          end: {
	            line: 148,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 148,
	            column: 19
	          },
	          end: {
	            line: 180,
	            column: 3
	          }
	        },
	        line: 148
	      },
	      "6": {
	        name: "(anonymous_6)",
	        decl: {
	          start: {
	            line: 187,
	            column: 2
	          },
	          end: {
	            line: 187,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 187,
	            column: 20
	          },
	          end: {
	            line: 199,
	            column: 3
	          }
	        },
	        line: 187
	      }
	    },
	    branchMap: {
	      "0": {
	        loc: {
	          start: {
	            line: 7,
	            column: 12
	          },
	          end: {
	            line: 7,
	            column: 34
	          }
	        },
	        type: "binary-expr",
	        locations: [{
	          start: {
	            line: 7,
	            column: 12
	          },
	          end: {
	            line: 7,
	            column: 23
	          }
	        }, {
	          start: {
	            line: 7,
	            column: 27
	          },
	          end: {
	            line: 7,
	            column: 34
	          }
	        }],
	        line: 7
	      },
	      "1": {
	        loc: {
	          start: {
	            line: 41,
	            column: 22
	          },
	          end: {
	            line: 41,
	            column: 34
	          }
	        },
	        type: "default-arg",
	        locations: [{
	          start: {
	            line: 41,
	            column: 32
	          },
	          end: {
	            line: 41,
	            column: 34
	          }
	        }],
	        line: 41
	      },
	      "2": {
	        loc: {
	          start: {
	            line: 86,
	            column: 31
	          },
	          end: {
	            line: 86,
	            column: 59
	          }
	        },
	        type: "binary-expr",
	        locations: [{
	          start: {
	            line: 86,
	            column: 31
	          },
	          end: {
	            line: 86,
	            column: 53
	          }
	        }, {
	          start: {
	            line: 86,
	            column: 57
	          },
	          end: {
	            line: 86,
	            column: 59
	          }
	        }],
	        line: 86
	      },
	      "3": {
	        loc: {
	          start: {
	            line: 91,
	            column: 4
	          },
	          end: {
	            line: 93,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 91,
	            column: 4
	          },
	          end: {
	            line: 93,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 91,
	            column: 4
	          },
	          end: {
	            line: 93,
	            column: 5
	          }
	        }],
	        line: 91
	      },
	      "4": {
	        loc: {
	          start: {
	            line: 113,
	            column: 6
	          },
	          end: {
	            line: 115,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 113,
	            column: 6
	          },
	          end: {
	            line: 115,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 113,
	            column: 6
	          },
	          end: {
	            line: 115,
	            column: 7
	          }
	        }],
	        line: 113
	      },
	      "5": {
	        loc: {
	          start: {
	            line: 124,
	            column: 4
	          },
	          end: {
	            line: 126,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 124,
	            column: 4
	          },
	          end: {
	            line: 126,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 124,
	            column: 4
	          },
	          end: {
	            line: 126,
	            column: 5
	          }
	        }],
	        line: 124
	      },
	      "6": {
	        loc: {
	          start: {
	            line: 124,
	            column: 8
	          },
	          end: {
	            line: 124,
	            column: 82
	          }
	        },
	        type: "binary-expr",
	        locations: [{
	          start: {
	            line: 124,
	            column: 8
	          },
	          end: {
	            line: 124,
	            column: 32
	          }
	        }, {
	          start: {
	            line: 124,
	            column: 36
	          },
	          end: {
	            line: 124,
	            column: 82
	          }
	        }],
	        line: 124
	      },
	      "7": {
	        loc: {
	          start: {
	            line: 149,
	            column: 4
	          },
	          end: {
	            line: 151,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 149,
	            column: 4
	          },
	          end: {
	            line: 151,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 149,
	            column: 4
	          },
	          end: {
	            line: 151,
	            column: 5
	          }
	        }],
	        line: 149
	      },
	      "8": {
	        loc: {
	          start: {
	            line: 153,
	            column: 4
	          },
	          end: {
	            line: 179,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 153,
	            column: 4
	          },
	          end: {
	            line: 179,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 153,
	            column: 4
	          },
	          end: {
	            line: 179,
	            column: 5
	          }
	        }],
	        line: 153
	      },
	      "9": {
	        loc: {
	          start: {
	            line: 159,
	            column: 37
	          },
	          end: {
	            line: 159,
	            column: 75
	          }
	        },
	        type: "cond-expr",
	        locations: [{
	          start: {
	            line: 159,
	            column: 51
	          },
	          end: {
	            line: 159,
	            column: 62
	          }
	        }, {
	          start: {
	            line: 159,
	            column: 65
	          },
	          end: {
	            line: 159,
	            column: 75
	          }
	        }],
	        line: 159
	      },
	      "10": {
	        loc: {
	          start: {
	            line: 164,
	            column: 6
	          },
	          end: {
	            line: 172,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 164,
	            column: 6
	          },
	          end: {
	            line: 172,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 164,
	            column: 6
	          },
	          end: {
	            line: 172,
	            column: 7
	          }
	        }],
	        line: 164
	      }
	    },
	    s: {
	      "0": 0,
	      "1": 0,
	      "2": 0,
	      "3": 0,
	      "4": 0,
	      "5": 0,
	      "6": 0,
	      "7": 0,
	      "8": 0,
	      "9": 0,
	      "10": 0,
	      "11": 0,
	      "12": 0,
	      "13": 0,
	      "14": 0,
	      "15": 0,
	      "16": 0,
	      "17": 0,
	      "18": 0,
	      "19": 0,
	      "20": 0,
	      "21": 0,
	      "22": 0,
	      "23": 0,
	      "24": 0,
	      "25": 0,
	      "26": 0,
	      "27": 0,
	      "28": 0,
	      "29": 0,
	      "30": 0,
	      "31": 0,
	      "32": 0,
	      "33": 0,
	      "34": 0,
	      "35": 0,
	      "36": 0,
	      "37": 0,
	      "38": 0,
	      "39": 0,
	      "40": 0,
	      "41": 0,
	      "42": 0,
	      "43": 0,
	      "44": 0,
	      "45": 0,
	      "46": 0,
	      "47": 0,
	      "48": 0,
	      "49": 0,
	      "50": 0,
	      "51": 0,
	      "52": 0,
	      "53": 0,
	      "54": 0,
	      "55": 0,
	      "56": 0,
	      "57": 0,
	      "58": 0,
	      "59": 0,
	      "60": 0
	    },
	    f: {
	      "0": 0,
	      "1": 0,
	      "2": 0,
	      "3": 0,
	      "4": 0,
	      "5": 0,
	      "6": 0
	    },
	    b: {
	      "0": [0, 0],
	      "1": [0],
	      "2": [0, 0],
	      "3": [0, 0],
	      "4": [0, 0],
	      "5": [0, 0],
	      "6": [0, 0],
	      "7": [0, 0],
	      "8": [0, 0],
	      "9": [0, 0],
	      "10": [0, 0]
	    },
	    _coverageSchema: "1a1c01bbd47fc00a2c39e90264f33305004495a9",
	    hash: "51bc091ed3445969dd1592c19f624ce772bc998c"
	  };
	  var coverage = global[gcv] || (global[gcv] = {});
	  if (!coverage[path] || coverage[path].hash !== hash) {
	    coverage[path] = coverageData;
	  }
	  var actualCoverage = coverage[path];
	  {
	    // @ts-ignore
	    cov_5brpejo2b = function () {
	      return actualCoverage;
	    };
	  }
	  return actualCoverage;
	}
	cov_5brpejo2b(); /**
	                 * @file quality-menu-item.js
	                 */
	const MenuItem = (cov_5brpejo2b().s[0]++, videojs__default["default"].getComponent('MenuItem'));
	const dom = (cov_5brpejo2b().s[1]++, (cov_5brpejo2b().b[0][0]++, videojs__default["default"].dom) || (cov_5brpejo2b().b[0][1]++, videojs__default["default"])); /**
	                                                                                                                        * The quality level menu quality
	                                                                                                                        *
	                                                                                                                        * @extends MenuItem
	                                                                                                                        * @class QualityMenuItem
	                                                                                                                        */
	class QualityMenuItem extends MenuItem {
	  /**
	  * Creates a QualityMenuItem
	  *
	  * @param {Player|Object} player
	  *        Main Player
	  * @param {Object} [options]
	  *        Options for menu item
	  * @param {number[]} options.levels
	  *        Array of indices mapping to QualityLevels in the QualityLevelList for
	  *        this menu item
	  * @param {string} options.label
	  *        Label for this menu item
	  * @param {string} options.controlText
	  *        control text for this menu item
	  * @param {string} options.subLabel
	  *        sub label text for this menu item
	  * @param {boolean} options.active
	  *        True if the QualityLevelList.selectedIndex is contained in the levels list
	  *        for this menu
	  * @param {boolean} options.selected
	  *        True if this menu item is the selected item in the UI
	  * @param {boolean} options.selectable
	  *        True if this menu item should be selectable in the UI
	  */
	  constructor(player, options = (cov_5brpejo2b().b[1][0]++, {})) {
	    cov_5brpejo2b().f[0]++;
	    const selectedOption = (cov_5brpejo2b().s[2]++, options.selected); // We need to change options.seleted to options.active because the call to super
	    // causes us to run MenuItem's constructor which calls this.selected(options.selected)
	    // However, for QualityMenuItem, we change the meaning of the parameter to
	    // this.selected() to be what we mean for 'active' which is True if the
	    // QualityLevelList.selectedIndex is contained in the levels list for this menu
	    cov_5brpejo2b().s[3]++;
	    options.selected = options.active;
	    cov_5brpejo2b().s[4]++;
	    super(player, options);
	    const qualityLevels = (cov_5brpejo2b().s[5]++, player.qualityLevels());
	    cov_5brpejo2b().s[6]++;
	    this.levels_ = options.levels;
	    cov_5brpejo2b().s[7]++;
	    this.selected_ = selectedOption;
	    cov_5brpejo2b().s[8]++;
	    this.handleQualityChange = this.handleQualityChange.bind(this);
	    cov_5brpejo2b().s[9]++;
	    this.controlText(options.controlText);
	    cov_5brpejo2b().s[10]++;
	    this.on(qualityLevels, 'change', this.handleQualityChange);
	    cov_5brpejo2b().s[11]++;
	    this.on('dispose', () => {
	      cov_5brpejo2b().f[1]++;
	      cov_5brpejo2b().s[12]++;
	      this.off(qualityLevels, 'change', this.handleQualityChange);
	    });
	  } /**
	    * Create the component's DOM element
	    *
	    * @param {string} [type]
	    *        Element type
	    * @param {Object} [props]
	    *        Element properties
	    * @param {Object} [attrs]
	    *        An object of attributes that should be set on the element
	    * @return {Element}
	    *         The DOM element
	    * @method createEl
	    */
	  createEl(type, props, attrs) {
	    cov_5brpejo2b().f[2]++;
	    const el = (cov_5brpejo2b().s[13]++, super.createEl(type, props, attrs));
	    const subLabel = (cov_5brpejo2b().s[14]++, dom.createEl('span', {
	      className: 'vjs-quality-menu-item-sub-label',
	      innerHTML: this.localize((cov_5brpejo2b().b[2][0]++, this.options_.subLabel) || (cov_5brpejo2b().b[2][1]++, ''))
	    }));
	    cov_5brpejo2b().s[15]++;
	    this.subLabel_ = subLabel;
	    cov_5brpejo2b().s[16]++;
	    if (el) {
	      cov_5brpejo2b().b[3][0]++;
	      cov_5brpejo2b().s[17]++;
	      el.appendChild(subLabel);
	    } else {
	      cov_5brpejo2b().b[3][1]++;
	    }
	    cov_5brpejo2b().s[18]++;
	    return el;
	  } /**
	    * Handle a click on the menu item, and set it to selected
	    *
	    * @method handleClick
	    */
	  handleClick() {
	    cov_5brpejo2b().f[3]++;
	    cov_5brpejo2b().s[19]++;
	    this.updateSiblings_();
	    const qualityLevels = (cov_5brpejo2b().s[20]++, this.player().qualityLevels());
	    const currentlySelected = (cov_5brpejo2b().s[21]++, qualityLevels.selectedIndex);
	    cov_5brpejo2b().s[22]++;
	    for (let i = (cov_5brpejo2b().s[23]++, 0), l = (cov_5brpejo2b().s[24]++, qualityLevels.length); i < l; i++) {
	      cov_5brpejo2b().s[25]++; // do not disable the currently selected quality until the end to prevent
	      // playlist selection from selecting something new until we've enabled/disabled
	      // all the quality levels
	      if (i !== currentlySelected) {
	        cov_5brpejo2b().b[4][0]++;
	        cov_5brpejo2b().s[26]++;
	        qualityLevels[i].enabled = false;
	      } else {
	        cov_5brpejo2b().b[4][1]++;
	      }
	    }
	    cov_5brpejo2b().s[27]++;
	    for (let i = (cov_5brpejo2b().s[28]++, 0), l = (cov_5brpejo2b().s[29]++, this.levels_.length); i < l; i++) {
	      cov_5brpejo2b().s[30]++;
	      qualityLevels[this.levels_[i]].enabled = true;
	    } // Disable the quality level that was selected before the click if it is not
	    // associated with this menu item
	    cov_5brpejo2b().s[31]++;
	    if ((cov_5brpejo2b().b[6][0]++, currentlySelected !== -1) && (cov_5brpejo2b().b[6][1]++, this.levels_.indexOf(currentlySelected) === -1)) {
	      cov_5brpejo2b().b[5][0]++;
	      cov_5brpejo2b().s[32]++;
	      qualityLevels[currentlySelected].enabled = false;
	    } else {
	      cov_5brpejo2b().b[5][1]++;
	    }
	  } /**
	    * Handle a change event from the QualityLevelList
	    *
	    * @method handleQualityChange
	    */
	  handleQualityChange() {
	    cov_5brpejo2b().f[4]++;
	    const qualityLevels = (cov_5brpejo2b().s[33]++, this.player().qualityLevels());
	    const active = (cov_5brpejo2b().s[34]++, this.levels_.indexOf(qualityLevels.selectedIndex) > -1);
	    cov_5brpejo2b().s[35]++;
	    this.selected(active);
	  } /**
	    * Set this menu item as selected or not
	    *
	    * @param  {boolean} active
	    *         True if the active quality level is controlled by this item
	    * @method selected
	    */
	  selected(active) {
	    cov_5brpejo2b().f[5]++;
	    cov_5brpejo2b().s[36]++;
	    if (!this.selectable) {
	      cov_5brpejo2b().b[7][0]++;
	      cov_5brpejo2b().s[37]++;
	      return;
	    } else {
	      cov_5brpejo2b().b[7][1]++;
	    }
	    cov_5brpejo2b().s[38]++;
	    if (this.selected_) {
	      cov_5brpejo2b().b[8][0]++;
	      const hasSubLabel = (cov_5brpejo2b().s[39]++, this.options_.subLabel);
	      cov_5brpejo2b().s[40]++;
	      this.addClass('vjs-selected');
	      cov_5brpejo2b().s[41]++;
	      this.el_.setAttribute('aria-checked', 'true'); // aria-checked isn't fully supported by browsers/screen readers,
	      // so indicate selected state to screen reader in the control text.
	      cov_5brpejo2b().s[42]++;
	      this.controlText(this.localize(hasSubLabel ? (cov_5brpejo2b().b[9][0]++, 'selected,') : (cov_5brpejo2b().b[9][1]++, 'selected')));
	      const controlBar = (cov_5brpejo2b().s[43]++, this.player().controlBar);
	      const menuButton = (cov_5brpejo2b().s[44]++, controlBar.getChild('QualityMenuButton'));
	      cov_5brpejo2b().s[45]++;
	      if (!active) {
	        cov_5brpejo2b().b[10][0]++;
	        cov_5brpejo2b().s[46]++; // This menu item is manually selected but the current playing quality level
	        // is NOT associated with this menu item. This can happen if the quality hasnt
	        // changed yet or something went wrong with rendition selection such as failed
	        // server responses for playlists
	        menuButton.addClass('vjs-quality-menu-button-waiting');
	      } else {
	        cov_5brpejo2b().b[10][1]++;
	        cov_5brpejo2b().s[47]++;
	        menuButton.removeClass('vjs-quality-menu-button-waiting');
	      }
	    } else {
	      cov_5brpejo2b().b[8][1]++;
	      cov_5brpejo2b().s[48]++;
	      this.removeClass('vjs-selected');
	      cov_5brpejo2b().s[49]++;
	      this.el_.setAttribute('aria-checked', 'false'); // Indicate un-selected state to screen reader
	      // Note that a space clears out the selected state text
	      cov_5brpejo2b().s[50]++;
	      this.controlText(this.options_.controlText);
	    }
	  } /**
	    * Sets this QualityMenuItem to be selected and deselects the other items
	    *
	    * @method updateSiblings_
	    */
	  updateSiblings_() {
	    cov_5brpejo2b().f[6]++;
	    const qualityLevels = (cov_5brpejo2b().s[51]++, this.player().qualityLevels());
	    const controlBar = (cov_5brpejo2b().s[52]++, this.player().controlBar);
	    const menuItems = (cov_5brpejo2b().s[53]++, controlBar.getChild('QualityMenuButton').items);
	    cov_5brpejo2b().s[54]++;
	    for (let i = (cov_5brpejo2b().s[55]++, 0), l = (cov_5brpejo2b().s[56]++, menuItems.length); i < l; i++) {
	      const item = (cov_5brpejo2b().s[57]++, menuItems[i]);
	      const active = (cov_5brpejo2b().s[58]++, item.levels_.indexOf(qualityLevels.selectedIndex) > -1);
	      cov_5brpejo2b().s[59]++;
	      item.selected_ = item === this;
	      cov_5brpejo2b().s[60]++;
	      item.selected(active);
	    }
	  }
	}

	function cov_6v7qpwivx() {
	  var path = "/Users/srimron-soutter/repos/github/videojs-contrib-quality-menu/src/quality-menu-button.js";
	  var hash = "b110675ff3cb80c47a4b25302fa674382f5a0f8c";
	  var global = new Function("return this")();
	  var gcv = "__coverage__";
	  var coverageData = {
	    path: "/Users/srimron-soutter/repos/github/videojs-contrib-quality-menu/src/quality-menu-button.js",
	    statementMap: {
	      "0": {
	        start: {
	          line: 7,
	          column: 19
	        },
	        end: {
	          line: 7,
	          column: 53
	        }
	      },
	      "1": {
	        start: {
	          line: 18,
	          column: 26
	        },
	        end: {
	          line: 20,
	          column: 1
	        }
	      },
	      "2": {
	        start: {
	          line: 19,
	          column: 2
	        },
	        end: {
	          line: 19,
	          column: 68
	        }
	      },
	      "3": {
	        start: {
	          line: 19,
	          column: 54
	        },
	        end: {
	          line: 19,
	          column: 66
	        }
	      },
	      "4": {
	        start: {
	          line: 31,
	          column: 20
	        },
	        end: {
	          line: 39,
	          column: 1
	        }
	      },
	      "5": {
	        start: {
	          line: 32,
	          column: 2
	        },
	        end: {
	          line: 34,
	          column: 3
	        }
	      },
	      "6": {
	        start: {
	          line: 33,
	          column: 4
	        },
	        end: {
	          line: 33,
	          column: 16
	        }
	      },
	      "7": {
	        start: {
	          line: 35,
	          column: 2
	        },
	        end: {
	          line: 37,
	          column: 3
	        }
	      },
	      "8": {
	        start: {
	          line: 36,
	          column: 4
	        },
	        end: {
	          line: 36,
	          column: 16
	        }
	      },
	      "9": {
	        start: {
	          line: 38,
	          column: 2
	        },
	        end: {
	          line: 38,
	          column: 12
	        }
	      },
	      "10": {
	        start: {
	          line: 58,
	          column: 4
	        },
	        end: {
	          line: 58,
	          column: 27
	        }
	      },
	      "11": {
	        start: {
	          line: 60,
	          column: 4
	        },
	        end: {
	          line: 60,
	          column: 73
	        }
	      },
	      "12": {
	        start: {
	          line: 61,
	          column: 4
	        },
	        end: {
	          line: 61,
	          column: 39
	        }
	      },
	      "13": {
	        start: {
	          line: 62,
	          column: 4
	        },
	        end: {
	          line: 64,
	          column: 5
	        }
	      },
	      "14": {
	        start: {
	          line: 63,
	          column: 6
	        },
	        end: {
	          line: 63,
	          column: 68
	        }
	      },
	      "15": {
	        start: {
	          line: 65,
	          column: 4
	        },
	        end: {
	          line: 65,
	          column: 24
	        }
	      },
	      "16": {
	        start: {
	          line: 67,
	          column: 4
	        },
	        end: {
	          line: 67,
	          column: 49
	        }
	      },
	      "17": {
	        start: {
	          line: 69,
	          column: 4
	        },
	        end: {
	          line: 69,
	          column: 41
	        }
	      },
	      "18": {
	        start: {
	          line: 70,
	          column: 4
	        },
	        end: {
	          line: 70,
	          column: 37
	        }
	      },
	      "19": {
	        start: {
	          line: 72,
	          column: 4
	        },
	        end: {
	          line: 72,
	          column: 69
	        }
	      },
	      "20": {
	        start: {
	          line: 73,
	          column: 4
	        },
	        end: {
	          line: 73,
	          column: 67
	        }
	      },
	      "21": {
	        start: {
	          line: 74,
	          column: 4
	        },
	        end: {
	          line: 74,
	          column: 77
	        }
	      },
	      "22": {
	        start: {
	          line: 76,
	          column: 4
	        },
	        end: {
	          line: 76,
	          column: 65
	        }
	      },
	      "23": {
	        start: {
	          line: 77,
	          column: 4
	        },
	        end: {
	          line: 77,
	          column: 68
	        }
	      },
	      "24": {
	        start: {
	          line: 78,
	          column: 4
	        },
	        end: {
	          line: 78,
	          column: 70
	        }
	      },
	      "25": {
	        start: {
	          line: 81,
	          column: 4
	        },
	        end: {
	          line: 81,
	          column: 70
	        }
	      },
	      "26": {
	        start: {
	          line: 82,
	          column: 4
	        },
	        end: {
	          line: 82,
	          column: 36
	        }
	      },
	      "27": {
	        start: {
	          line: 83,
	          column: 4
	        },
	        end: {
	          line: 83,
	          column: 51
	        }
	      },
	      "28": {
	        start: {
	          line: 85,
	          column: 4
	        },
	        end: {
	          line: 85,
	          column: 18
	        }
	      },
	      "29": {
	        start: {
	          line: 87,
	          column: 4
	        },
	        end: {
	          line: 95,
	          column: 7
	        }
	      },
	      "30": {
	        start: {
	          line: 88,
	          column: 6
	        },
	        end: {
	          line: 88,
	          column: 68
	        }
	      },
	      "31": {
	        start: {
	          line: 89,
	          column: 6
	        },
	        end: {
	          line: 89,
	          column: 71
	        }
	      },
	      "32": {
	        start: {
	          line: 90,
	          column: 6
	        },
	        end: {
	          line: 90,
	          column: 73
	        }
	      },
	      "33": {
	        start: {
	          line: 91,
	          column: 6
	        },
	        end: {
	          line: 91,
	          column: 72
	        }
	      },
	      "34": {
	        start: {
	          line: 92,
	          column: 6
	        },
	        end: {
	          line: 92,
	          column: 39
	        }
	      },
	      "35": {
	        start: {
	          line: 93,
	          column: 6
	        },
	        end: {
	          line: 93,
	          column: 54
	        }
	      },
	      "36": {
	        start: {
	          line: 94,
	          column: 6
	        },
	        end: {
	          line: 94,
	          column: 66
	        }
	      },
	      "37": {
	        start: {
	          line: 106,
	          column: 4
	        },
	        end: {
	          line: 106,
	          column: 70
	        }
	      },
	      "38": {
	        start: {
	          line: 117,
	          column: 4
	        },
	        end: {
	          line: 117,
	          column: 62
	        }
	      },
	      "39": {
	        start: {
	          line: 128,
	          column: 18
	        },
	        end: {
	          line: 128,
	          column: 20
	        }
	      },
	      "40": {
	        start: {
	          line: 130,
	          column: 4
	        },
	        end: {
	          line: 132,
	          column: 5
	        }
	      },
	      "41": {
	        start: {
	          line: 131,
	          column: 6
	        },
	        end: {
	          line: 131,
	          column: 19
	        }
	      },
	      "42": {
	        start: {
	          line: 136,
	          column: 4
	        },
	        end: {
	          line: 142,
	          column: 5
	        }
	      },
	      "43": {
	        start: {
	          line: 137,
	          column: 6
	        },
	        end: {
	          line: 137,
	          column: 41
	        }
	      },
	      "44": {
	        start: {
	          line: 138,
	          column: 6
	        },
	        end: {
	          line: 138,
	          column: 62
	        }
	      },
	      "45": {
	        start: {
	          line: 140,
	          column: 6
	        },
	        end: {
	          line: 140,
	          column: 38
	        }
	      },
	      "46": {
	        start: {
	          line: 141,
	          column: 6
	        },
	        end: {
	          line: 141,
	          column: 65
	        }
	      },
	      "47": {
	        start: {
	          line: 148,
	          column: 4
	        },
	        end: {
	          line: 150,
	          column: 5
	        }
	      },
	      "48": {
	        start: {
	          line: 149,
	          column: 6
	        },
	        end: {
	          line: 149,
	          column: 16
	        }
	      },
	      "49": {
	        start: {
	          line: 152,
	          column: 4
	        },
	        end: {
	          line: 158,
	          column: 7
	        }
	      },
	      "50": {
	        start: {
	          line: 153,
	          column: 6
	        },
	        end: {
	          line: 157,
	          column: 7
	        }
	      },
	      "51": {
	        start: {
	          line: 154,
	          column: 8
	        },
	        end: {
	          line: 154,
	          column: 32
	        }
	      },
	      "52": {
	        start: {
	          line: 156,
	          column: 8
	        },
	        end: {
	          line: 156,
	          column: 62
	        }
	      },
	      "53": {
	        start: {
	          line: 161,
	          column: 17
	        },
	        end: {
	          line: 168,
	          column: 6
	        }
	      },
	      "54": {
	        start: {
	          line: 162,
	          column: 74
	        },
	        end: {
	          line: 162,
	          column: 75
	        }
	      },
	      "55": {
	        start: {
	          line: 170,
	          column: 4
	        },
	        end: {
	          line: 170,
	          column: 30
	        }
	      },
	      "56": {
	        start: {
	          line: 172,
	          column: 4
	        },
	        end: {
	          line: 172,
	          column: 21
	        }
	      },
	      "57": {
	        start: {
	          line: 174,
	          column: 4
	        },
	        end: {
	          line: 174,
	          column: 17
	        }
	      },
	      "58": {
	        start: {
	          line: 185,
	          column: 19
	        },
	        end: {
	          line: 185,
	          column: 21
	        }
	      },
	      "59": {
	        start: {
	          line: 186,
	          column: 18
	        },
	        end: {
	          line: 186,
	          column: 20
	        }
	      },
	      "60": {
	        start: {
	          line: 188,
	          column: 4
	        },
	        end: {
	          line: 226,
	          column: 5
	        }
	      },
	      "61": {
	        start: {
	          line: 188,
	          column: 17
	        },
	        end: {
	          line: 188,
	          column: 18
	        }
	      },
	      "62": {
	        start: {
	          line: 188,
	          column: 24
	        },
	        end: {
	          line: 188,
	          column: 50
	        }
	      },
	      "63": {
	        start: {
	          line: 189,
	          column: 20
	        },
	        end: {
	          line: 189,
	          column: 42
	        }
	      },
	      "64": {
	        start: {
	          line: 190,
	          column: 21
	        },
	        end: {
	          line: 190,
	          column: 60
	        }
	      },
	      "65": {
	        start: {
	          line: 191,
	          column: 20
	        },
	        end: {
	          line: 191,
	          column: 32
	        }
	      },
	      "66": {
	        start: {
	          line: 194,
	          column: 6
	        },
	        end: {
	          line: 196,
	          column: 7
	        }
	      },
	      "67": {
	        start: {
	          line: 195,
	          column: 8
	        },
	        end: {
	          line: 195,
	          column: 17
	        }
	      },
	      "68": {
	        start: {
	          line: 200,
	          column: 6
	        },
	        end: {
	          line: 206,
	          column: 7
	        }
	      },
	      "69": {
	        start: {
	          line: 201,
	          column: 23
	        },
	        end: {
	          line: 201,
	          column: 55
	        }
	      },
	      "70": {
	        start: {
	          line: 203,
	          column: 8
	        },
	        end: {
	          line: 203,
	          column: 45
	        }
	      },
	      "71": {
	        start: {
	          line: 205,
	          column: 8
	        },
	        end: {
	          line: 205,
	          column: 28
	        }
	      },
	      "72": {
	        start: {
	          line: 208,
	          column: 6
	        },
	        end: {
	          line: 219,
	          column: 7
	        }
	      },
	      "73": {
	        start: {
	          line: 209,
	          column: 25
	        },
	        end: {
	          line: 209,
	          column: 43
	        }
	      },
	      "74": {
	        start: {
	          line: 211,
	          column: 8
	        },
	        end: {
	          line: 216,
	          column: 10
	        }
	      },
	      "75": {
	        start: {
	          line: 218,
	          column: 8
	        },
	        end: {
	          line: 218,
	          column: 37
	        }
	      },
	      "76": {
	        start: {
	          line: 221,
	          column: 6
	        },
	        end: {
	          line: 223,
	          column: 7
	        }
	      },
	      "77": {
	        start: {
	          line: 222,
	          column: 8
	        },
	        end: {
	          line: 222,
	          column: 36
	        }
	      },
	      "78": {
	        start: {
	          line: 225,
	          column: 6
	        },
	        end: {
	          line: 225,
	          column: 35
	        }
	      },
	      "79": {
	        start: {
	          line: 229,
	          column: 4
	        },
	        end: {
	          line: 229,
	          column: 44
	        }
	      },
	      "80": {
	        start: {
	          line: 229,
	          column: 25
	        },
	        end: {
	          line: 229,
	          column: 42
	        }
	      },
	      "81": {
	        start: {
	          line: 231,
	          column: 25
	        },
	        end: {
	          line: 231,
	          column: 27
	        }
	      },
	      "82": {
	        start: {
	          line: 233,
	          column: 4
	        },
	        end: {
	          line: 235,
	          column: 7
	        }
	      },
	      "83": {
	        start: {
	          line: 234,
	          column: 6
	        },
	        end: {
	          line: 234,
	          column: 45
	        }
	      },
	      "84": {
	        start: {
	          line: 237,
	          column: 4
	        },
	        end: {
	          line: 237,
	          column: 24
	        }
	      },
	      "85": {
	        start: {
	          line: 249,
	          column: 19
	        },
	        end: {
	          line: 260,
	          column: 5
	        }
	      },
	      "86": {
	        start: {
	          line: 262,
	          column: 4
	        },
	        end: {
	          line: 279,
	          column: 5
	        }
	      },
	      "87": {
	        start: {
	          line: 262,
	          column: 17
	        },
	        end: {
	          line: 262,
	          column: 18
	        }
	      },
	      "88": {
	        start: {
	          line: 262,
	          column: 24
	        },
	        end: {
	          line: 262,
	          column: 50
	        }
	      },
	      "89": {
	        start: {
	          line: 263,
	          column: 20
	        },
	        end: {
	          line: 263,
	          column: 42
	        }
	      },
	      "90": {
	        start: {
	          line: 264,
	          column: 21
	        },
	        end: {
	          line: 264,
	          column: 60
	        }
	      },
	      "91": {
	        start: {
	          line: 268,
	          column: 6
	        },
	        end: {
	          line: 272,
	          column: 7
	        }
	      },
	      "92": {
	        start: {
	          line: 269,
	          column: 8
	        },
	        end: {
	          line: 269,
	          column: 26
	        }
	      },
	      "93": {
	        start: {
	          line: 271,
	          column: 8
	        },
	        end: {
	          line: 271,
	          column: 26
	        }
	      },
	      "94": {
	        start: {
	          line: 274,
	          column: 6
	        },
	        end: {
	          line: 276,
	          column: 7
	        }
	      },
	      "95": {
	        start: {
	          line: 275,
	          column: 8
	        },
	        end: {
	          line: 275,
	          column: 28
	        }
	      },
	      "96": {
	        start: {
	          line: 278,
	          column: 6
	        },
	        end: {
	          line: 278,
	          column: 27
	        }
	      },
	      "97": {
	        start: {
	          line: 281,
	          column: 4
	        },
	        end: {
	          line: 287,
	          column: 5
	        }
	      },
	      "98": {
	        start: {
	          line: 286,
	          column: 6
	        },
	        end: {
	          line: 286,
	          column: 16
	        }
	      },
	      "99": {
	        start: {
	          line: 289,
	          column: 4
	        },
	        end: {
	          line: 289,
	          column: 18
	        }
	      },
	      "100": {
	        start: {
	          line: 298,
	          column: 21
	        },
	        end: {
	          line: 298,
	          column: 75
	        }
	      },
	      "101": {
	        start: {
	          line: 299,
	          column: 27
	        },
	        end: {
	          line: 300,
	          column: 65
	        }
	      },
	      "102": {
	        start: {
	          line: 302,
	          column: 19
	        },
	        end: {
	          line: 302,
	          column: 21
	        }
	      },
	      "103": {
	        start: {
	          line: 304,
	          column: 4
	        },
	        end: {
	          line: 310,
	          column: 5
	        }
	      },
	      "104": {
	        start: {
	          line: 305,
	          column: 6
	        },
	        end: {
	          line: 309,
	          column: 7
	        }
	      },
	      "105": {
	        start: {
	          line: 306,
	          column: 8
	        },
	        end: {
	          line: 306,
	          column: 48
	        }
	      },
	      "106": {
	        start: {
	          line: 307,
	          column: 13
	        },
	        end: {
	          line: 309,
	          column: 7
	        }
	      },
	      "107": {
	        start: {
	          line: 308,
	          column: 8
	        },
	        end: {
	          line: 308,
	          column: 24
	        }
	      },
	      "108": {
	        start: {
	          line: 312,
	          column: 4
	        },
	        end: {
	          line: 321,
	          column: 5
	        }
	      },
	      "109": {
	        start: {
	          line: 313,
	          column: 6
	        },
	        end: {
	          line: 313,
	          column: 55
	        }
	      },
	      "110": {
	        start: {
	          line: 314,
	          column: 6
	        },
	        end: {
	          line: 314,
	          column: 58
	        }
	      },
	      "111": {
	        start: {
	          line: 315,
	          column: 11
	        },
	        end: {
	          line: 321,
	          column: 5
	        }
	      },
	      "112": {
	        start: {
	          line: 316,
	          column: 6
	        },
	        end: {
	          line: 316,
	          column: 58
	        }
	      },
	      "113": {
	        start: {
	          line: 317,
	          column: 6
	        },
	        end: {
	          line: 317,
	          column: 55
	        }
	      },
	      "114": {
	        start: {
	          line: 319,
	          column: 6
	        },
	        end: {
	          line: 319,
	          column: 58
	        }
	      },
	      "115": {
	        start: {
	          line: 320,
	          column: 6
	        },
	        end: {
	          line: 320,
	          column: 58
	        }
	      },
	      "116": {
	        start: {
	          line: 323,
	          column: 4
	        },
	        end: {
	          line: 330,
	          column: 5
	        }
	      },
	      "117": {
	        start: {
	          line: 324,
	          column: 6
	        },
	        end: {
	          line: 329,
	          column: 7
	        }
	      },
	      "118": {
	        start: {
	          line: 326,
	          column: 8
	        },
	        end: {
	          line: 326,
	          column: 73
	        }
	      },
	      "119": {
	        start: {
	          line: 328,
	          column: 8
	        },
	        end: {
	          line: 328,
	          column: 52
	        }
	      },
	      "120": {
	        start: {
	          line: 339,
	          column: 30
	        },
	        end: {
	          line: 339,
	          column: 61
	        }
	      },
	      "121": {
	        start: {
	          line: 341,
	          column: 4
	        },
	        end: {
	          line: 345,
	          column: 5
	        }
	      },
	      "122": {
	        start: {
	          line: 341,
	          column: 17
	        },
	        end: {
	          line: 341,
	          column: 18
	        }
	      },
	      "123": {
	        start: {
	          line: 342,
	          column: 6
	        },
	        end: {
	          line: 344,
	          column: 7
	        }
	      },
	      "124": {
	        start: {
	          line: 343,
	          column: 8
	        },
	        end: {
	          line: 343,
	          column: 36
	        }
	      },
	      "125": {
	        start: {
	          line: 356,
	          column: 4
	        },
	        end: {
	          line: 359,
	          column: 5
	        }
	      },
	      "126": {
	        start: {
	          line: 357,
	          column: 6
	        },
	        end: {
	          line: 357,
	          column: 72
	        }
	      },
	      "127": {
	        start: {
	          line: 358,
	          column: 6
	        },
	        end: {
	          line: 358,
	          column: 13
	        }
	      },
	      "128": {
	        start: {
	          line: 361,
	          column: 4
	        },
	        end: {
	          line: 361,
	          column: 36
	        }
	      },
	      "129": {
	        start: {
	          line: 365,
	          column: 0
	        },
	        end: {
	          line: 365,
	          column: 66
	        }
	      }
	    },
	    fnMap: {
	      "0": {
	        name: "(anonymous_0)",
	        decl: {
	          start: {
	            line: 18,
	            column: 26
	          },
	          end: {
	            line: 18,
	            column: 27
	          }
	        },
	        loc: {
	          start: {
	            line: 18,
	            column: 53
	          },
	          end: {
	            line: 20,
	            column: 1
	          }
	        },
	        line: 18
	      },
	      "1": {
	        name: "(anonymous_1)",
	        decl: {
	          start: {
	            line: 19,
	            column: 43
	          },
	          end: {
	            line: 19,
	            column: 44
	          }
	        },
	        loc: {
	          start: {
	            line: 19,
	            column: 54
	          },
	          end: {
	            line: 19,
	            column: 66
	          }
	        },
	        line: 19
	      },
	      "2": {
	        name: "(anonymous_2)",
	        decl: {
	          start: {
	            line: 31,
	            column: 20
	          },
	          end: {
	            line: 31,
	            column: 21
	          }
	        },
	        loc: {
	          start: {
	            line: 31,
	            column: 36
	          },
	          end: {
	            line: 39,
	            column: 1
	          }
	        },
	        line: 31
	      },
	      "3": {
	        name: "(anonymous_3)",
	        decl: {
	          start: {
	            line: 57,
	            column: 2
	          },
	          end: {
	            line: 57,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 57,
	            column: 36
	          },
	          end: {
	            line: 96,
	            column: 3
	          }
	        },
	        line: 57
	      },
	      "4": {
	        name: "(anonymous_4)",
	        decl: {
	          start: {
	            line: 87,
	            column: 23
	          },
	          end: {
	            line: 87,
	            column: 24
	          }
	        },
	        loc: {
	          start: {
	            line: 87,
	            column: 29
	          },
	          end: {
	            line: 95,
	            column: 5
	          }
	        },
	        line: 87
	      },
	      "5": {
	        name: "(anonymous_5)",
	        decl: {
	          start: {
	            line: 105,
	            column: 2
	          },
	          end: {
	            line: 105,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 105,
	            column: 25
	          },
	          end: {
	            line: 107,
	            column: 3
	          }
	        },
	        line: 105
	      },
	      "6": {
	        name: "(anonymous_6)",
	        decl: {
	          start: {
	            line: 116,
	            column: 2
	          },
	          end: {
	            line: 116,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 116,
	            column: 18
	          },
	          end: {
	            line: 118,
	            column: 3
	          }
	        },
	        line: 116
	      },
	      "7": {
	        name: "(anonymous_7)",
	        decl: {
	          start: {
	            line: 127,
	            column: 2
	          },
	          end: {
	            line: 127,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 127,
	            column: 16
	          },
	          end: {
	            line: 175,
	            column: 3
	          }
	        },
	        line: 127
	      },
	      "8": {
	        name: "(anonymous_8)",
	        decl: {
	          start: {
	            line: 152,
	            column: 19
	          },
	          end: {
	            line: 152,
	            column: 20
	          }
	        },
	        loc: {
	          start: {
	            line: 152,
	            column: 30
	          },
	          end: {
	            line: 158,
	            column: 5
	          }
	        },
	        line: 152
	      },
	      "9": {
	        name: "(anonymous_9)",
	        decl: {
	          start: {
	            line: 162,
	            column: 60
	          },
	          end: {
	            line: 162,
	            column: 61
	          }
	        },
	        loc: {
	          start: {
	            line: 162,
	            column: 74
	          },
	          end: {
	            line: 162,
	            column: 75
	          }
	        },
	        line: 162
	      },
	      "10": {
	        name: "(anonymous_10)",
	        decl: {
	          start: {
	            line: 184,
	            column: 2
	          },
	          end: {
	            line: 184,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 184,
	            column: 23
	          },
	          end: {
	            line: 238,
	            column: 3
	          }
	        },
	        line: 184
	      },
	      "11": {
	        name: "(anonymous_11)",
	        decl: {
	          start: {
	            line: 229,
	            column: 15
	          },
	          end: {
	            line: 229,
	            column: 16
	          }
	        },
	        loc: {
	          start: {
	            line: 229,
	            column: 25
	          },
	          end: {
	            line: 229,
	            column: 42
	          }
	        },
	        line: 229
	      },
	      "12": {
	        name: "(anonymous_12)",
	        decl: {
	          start: {
	            line: 233,
	            column: 18
	          },
	          end: {
	            line: 233,
	            column: 19
	          }
	        },
	        loc: {
	          start: {
	            line: 233,
	            column: 29
	          },
	          end: {
	            line: 235,
	            column: 5
	          }
	        },
	        line: 233
	      },
	      "13": {
	        name: "(anonymous_13)",
	        decl: {
	          start: {
	            line: 247,
	            column: 2
	          },
	          end: {
	            line: 247,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 247,
	            column: 20
	          },
	          end: {
	            line: 290,
	            column: 3
	          }
	        },
	        line: 247
	      },
	      "14": {
	        name: "(anonymous_14)",
	        decl: {
	          start: {
	            line: 297,
	            column: 2
	          },
	          end: {
	            line: 297,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 297,
	            column: 25
	          },
	          end: {
	            line: 331,
	            column: 3
	          }
	        },
	        line: 297
	      },
	      "15": {
	        name: "(anonymous_15)",
	        decl: {
	          start: {
	            line: 338,
	            column: 2
	          },
	          end: {
	            line: 338,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 338,
	            column: 29
	          },
	          end: {
	            line: 346,
	            column: 3
	          }
	        },
	        line: 338
	      },
	      "16": {
	        name: "(anonymous_16)",
	        decl: {
	          start: {
	            line: 353,
	            column: 2
	          },
	          end: {
	            line: 353,
	            column: 3
	          }
	        },
	        loc: {
	          start: {
	            line: 353,
	            column: 24
	          },
	          end: {
	            line: 362,
	            column: 3
	          }
	        },
	        line: 353
	      }
	    },
	    branchMap: {
	      "0": {
	        loc: {
	          start: {
	            line: 32,
	            column: 2
	          },
	          end: {
	            line: 34,
	            column: 3
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 32,
	            column: 2
	          },
	          end: {
	            line: 34,
	            column: 3
	          }
	        }, {
	          start: {
	            line: 32,
	            column: 2
	          },
	          end: {
	            line: 34,
	            column: 3
	          }
	        }],
	        line: 32
	      },
	      "1": {
	        loc: {
	          start: {
	            line: 35,
	            column: 2
	          },
	          end: {
	            line: 37,
	            column: 3
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 35,
	            column: 2
	          },
	          end: {
	            line: 37,
	            column: 3
	          }
	        }, {
	          start: {
	            line: 35,
	            column: 2
	          },
	          end: {
	            line: 37,
	            column: 3
	          }
	        }],
	        line: 35
	      },
	      "2": {
	        loc: {
	          start: {
	            line: 57,
	            column: 22
	          },
	          end: {
	            line: 57,
	            column: 34
	          }
	        },
	        type: "default-arg",
	        locations: [{
	          start: {
	            line: 57,
	            column: 32
	          },
	          end: {
	            line: 57,
	            column: 34
	          }
	        }],
	        line: 57
	      },
	      "3": {
	        loc: {
	          start: {
	            line: 62,
	            column: 4
	          },
	          end: {
	            line: 64,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 62,
	            column: 4
	          },
	          end: {
	            line: 64,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 62,
	            column: 4
	          },
	          end: {
	            line: 64,
	            column: 5
	          }
	        }],
	        line: 62
	      },
	      "4": {
	        loc: {
	          start: {
	            line: 130,
	            column: 4
	          },
	          end: {
	            line: 132,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 130,
	            column: 4
	          },
	          end: {
	            line: 132,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 130,
	            column: 4
	          },
	          end: {
	            line: 132,
	            column: 5
	          }
	        }],
	        line: 130
	      },
	      "5": {
	        loc: {
	          start: {
	            line: 130,
	            column: 10
	          },
	          end: {
	            line: 130,
	            column: 59
	          }
	        },
	        type: "binary-expr",
	        locations: [{
	          start: {
	            line: 130,
	            column: 10
	          },
	          end: {
	            line: 130,
	            column: 29
	          }
	        }, {
	          start: {
	            line: 130,
	            column: 33
	          },
	          end: {
	            line: 130,
	            column: 59
	          }
	        }],
	        line: 130
	      },
	      "6": {
	        loc: {
	          start: {
	            line: 136,
	            column: 4
	          },
	          end: {
	            line: 142,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 136,
	            column: 4
	          },
	          end: {
	            line: 142,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 136,
	            column: 4
	          },
	          end: {
	            line: 142,
	            column: 5
	          }
	        }],
	        line: 136
	      },
	      "7": {
	        loc: {
	          start: {
	            line: 136,
	            column: 8
	          },
	          end: {
	            line: 136,
	            column: 83
	          }
	        },
	        type: "binary-expr",
	        locations: [{
	          start: {
	            line: 136,
	            column: 8
	          },
	          end: {
	            line: 136,
	            column: 41
	          }
	        }, {
	          start: {
	            line: 136,
	            column: 45
	          },
	          end: {
	            line: 136,
	            column: 83
	          }
	        }],
	        line: 136
	      },
	      "8": {
	        loc: {
	          start: {
	            line: 148,
	            column: 4
	          },
	          end: {
	            line: 150,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 148,
	            column: 4
	          },
	          end: {
	            line: 150,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 148,
	            column: 4
	          },
	          end: {
	            line: 150,
	            column: 5
	          }
	        }],
	        line: 148
	      },
	      "9": {
	        loc: {
	          start: {
	            line: 153,
	            column: 6
	          },
	          end: {
	            line: 157,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 153,
	            column: 6
	          },
	          end: {
	            line: 157,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 153,
	            column: 6
	          },
	          end: {
	            line: 157,
	            column: 7
	          }
	        }],
	        line: 153
	      },
	      "10": {
	        loc: {
	          start: {
	            line: 194,
	            column: 6
	          },
	          end: {
	            line: 196,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 194,
	            column: 6
	          },
	          end: {
	            line: 196,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 194,
	            column: 6
	          },
	          end: {
	            line: 196,
	            column: 7
	          }
	        }],
	        line: 194
	      },
	      "11": {
	        loc: {
	          start: {
	            line: 200,
	            column: 6
	          },
	          end: {
	            line: 206,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 200,
	            column: 6
	          },
	          end: {
	            line: 206,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 200,
	            column: 6
	          },
	          end: {
	            line: 206,
	            column: 7
	          }
	        }],
	        line: 200
	      },
	      "12": {
	        loc: {
	          start: {
	            line: 208,
	            column: 6
	          },
	          end: {
	            line: 219,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 208,
	            column: 6
	          },
	          end: {
	            line: 219,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 208,
	            column: 6
	          },
	          end: {
	            line: 219,
	            column: 7
	          }
	        }],
	        line: 208
	      },
	      "13": {
	        loc: {
	          start: {
	            line: 221,
	            column: 6
	          },
	          end: {
	            line: 223,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 221,
	            column: 6
	          },
	          end: {
	            line: 223,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 221,
	            column: 6
	          },
	          end: {
	            line: 223,
	            column: 7
	          }
	        }],
	        line: 221
	      },
	      "14": {
	        loc: {
	          start: {
	            line: 268,
	            column: 6
	          },
	          end: {
	            line: 272,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 268,
	            column: 6
	          },
	          end: {
	            line: 272,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 268,
	            column: 6
	          },
	          end: {
	            line: 272,
	            column: 7
	          }
	        }],
	        line: 268
	      },
	      "15": {
	        loc: {
	          start: {
	            line: 274,
	            column: 6
	          },
	          end: {
	            line: 276,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 274,
	            column: 6
	          },
	          end: {
	            line: 276,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 274,
	            column: 6
	          },
	          end: {
	            line: 276,
	            column: 7
	          }
	        }],
	        line: 274
	      },
	      "16": {
	        loc: {
	          start: {
	            line: 281,
	            column: 4
	          },
	          end: {
	            line: 287,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 281,
	            column: 4
	          },
	          end: {
	            line: 287,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 281,
	            column: 4
	          },
	          end: {
	            line: 287,
	            column: 5
	          }
	        }],
	        line: 281
	      },
	      "17": {
	        loc: {
	          start: {
	            line: 281,
	            column: 8
	          },
	          end: {
	            line: 281,
	            column: 60
	          }
	        },
	        type: "binary-expr",
	        locations: [{
	          start: {
	            line: 281,
	            column: 8
	          },
	          end: {
	            line: 281,
	            column: 32
	          }
	        }, {
	          start: {
	            line: 281,
	            column: 36
	          },
	          end: {
	            line: 281,
	            column: 60
	          }
	        }],
	        line: 281
	      },
	      "18": {
	        loc: {
	          start: {
	            line: 299,
	            column: 27
	          },
	          end: {
	            line: 300,
	            column: 65
	          }
	        },
	        type: "binary-expr",
	        locations: [{
	          start: {
	            line: 299,
	            column: 27
	          },
	          end: {
	            line: 299,
	            column: 60
	          }
	        }, {
	          start: {
	            line: 300,
	            column: 27
	          },
	          end: {
	            line: 300,
	            column: 65
	          }
	        }],
	        line: 299
	      },
	      "19": {
	        loc: {
	          start: {
	            line: 304,
	            column: 4
	          },
	          end: {
	            line: 310,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 304,
	            column: 4
	          },
	          end: {
	            line: 310,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 304,
	            column: 4
	          },
	          end: {
	            line: 310,
	            column: 5
	          }
	        }],
	        line: 304
	      },
	      "20": {
	        loc: {
	          start: {
	            line: 305,
	            column: 6
	          },
	          end: {
	            line: 309,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 305,
	            column: 6
	          },
	          end: {
	            line: 309,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 305,
	            column: 6
	          },
	          end: {
	            line: 309,
	            column: 7
	          }
	        }],
	        line: 305
	      },
	      "21": {
	        loc: {
	          start: {
	            line: 307,
	            column: 13
	          },
	          end: {
	            line: 309,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 307,
	            column: 13
	          },
	          end: {
	            line: 309,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 307,
	            column: 13
	          },
	          end: {
	            line: 309,
	            column: 7
	          }
	        }],
	        line: 307
	      },
	      "22": {
	        loc: {
	          start: {
	            line: 312,
	            column: 4
	          },
	          end: {
	            line: 321,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 312,
	            column: 4
	          },
	          end: {
	            line: 321,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 312,
	            column: 4
	          },
	          end: {
	            line: 321,
	            column: 5
	          }
	        }],
	        line: 312
	      },
	      "23": {
	        loc: {
	          start: {
	            line: 315,
	            column: 11
	          },
	          end: {
	            line: 321,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 315,
	            column: 11
	          },
	          end: {
	            line: 321,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 315,
	            column: 11
	          },
	          end: {
	            line: 321,
	            column: 5
	          }
	        }],
	        line: 315
	      },
	      "24": {
	        loc: {
	          start: {
	            line: 323,
	            column: 4
	          },
	          end: {
	            line: 330,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 323,
	            column: 4
	          },
	          end: {
	            line: 330,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 323,
	            column: 4
	          },
	          end: {
	            line: 330,
	            column: 5
	          }
	        }],
	        line: 323
	      },
	      "25": {
	        loc: {
	          start: {
	            line: 324,
	            column: 6
	          },
	          end: {
	            line: 329,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 324,
	            column: 6
	          },
	          end: {
	            line: 329,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 324,
	            column: 6
	          },
	          end: {
	            line: 329,
	            column: 7
	          }
	        }],
	        line: 324
	      },
	      "26": {
	        loc: {
	          start: {
	            line: 324,
	            column: 10
	          },
	          end: {
	            line: 324,
	            column: 58
	          }
	        },
	        type: "binary-expr",
	        locations: [{
	          start: {
	            line: 324,
	            column: 10
	          },
	          end: {
	            line: 324,
	            column: 46
	          }
	        }, {
	          start: {
	            line: 324,
	            column: 50
	          },
	          end: {
	            line: 324,
	            column: 58
	          }
	        }],
	        line: 324
	      },
	      "27": {
	        loc: {
	          start: {
	            line: 342,
	            column: 6
	          },
	          end: {
	            line: 344,
	            column: 7
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 342,
	            column: 6
	          },
	          end: {
	            line: 344,
	            column: 7
	          }
	        }, {
	          start: {
	            line: 342,
	            column: 6
	          },
	          end: {
	            line: 344,
	            column: 7
	          }
	        }],
	        line: 342
	      },
	      "28": {
	        loc: {
	          start: {
	            line: 356,
	            column: 4
	          },
	          end: {
	            line: 359,
	            column: 5
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 356,
	            column: 4
	          },
	          end: {
	            line: 359,
	            column: 5
	          }
	        }, {
	          start: {
	            line: 356,
	            column: 4
	          },
	          end: {
	            line: 359,
	            column: 5
	          }
	        }],
	        line: 356
	      },
	      "29": {
	        loc: {
	          start: {
	            line: 356,
	            column: 8
	          },
	          end: {
	            line: 356,
	            column: 74
	          }
	        },
	        type: "binary-expr",
	        locations: [{
	          start: {
	            line: 356,
	            column: 8
	          },
	          end: {
	            line: 356,
	            column: 37
	          }
	        }, {
	          start: {
	            line: 356,
	            column: 41
	          },
	          end: {
	            line: 356,
	            column: 74
	          }
	        }],
	        line: 356
	      }
	    },
	    s: {
	      "0": 0,
	      "1": 0,
	      "2": 0,
	      "3": 0,
	      "4": 0,
	      "5": 0,
	      "6": 0,
	      "7": 0,
	      "8": 0,
	      "9": 0,
	      "10": 0,
	      "11": 0,
	      "12": 0,
	      "13": 0,
	      "14": 0,
	      "15": 0,
	      "16": 0,
	      "17": 0,
	      "18": 0,
	      "19": 0,
	      "20": 0,
	      "21": 0,
	      "22": 0,
	      "23": 0,
	      "24": 0,
	      "25": 0,
	      "26": 0,
	      "27": 0,
	      "28": 0,
	      "29": 0,
	      "30": 0,
	      "31": 0,
	      "32": 0,
	      "33": 0,
	      "34": 0,
	      "35": 0,
	      "36": 0,
	      "37": 0,
	      "38": 0,
	      "39": 0,
	      "40": 0,
	      "41": 0,
	      "42": 0,
	      "43": 0,
	      "44": 0,
	      "45": 0,
	      "46": 0,
	      "47": 0,
	      "48": 0,
	      "49": 0,
	      "50": 0,
	      "51": 0,
	      "52": 0,
	      "53": 0,
	      "54": 0,
	      "55": 0,
	      "56": 0,
	      "57": 0,
	      "58": 0,
	      "59": 0,
	      "60": 0,
	      "61": 0,
	      "62": 0,
	      "63": 0,
	      "64": 0,
	      "65": 0,
	      "66": 0,
	      "67": 0,
	      "68": 0,
	      "69": 0,
	      "70": 0,
	      "71": 0,
	      "72": 0,
	      "73": 0,
	      "74": 0,
	      "75": 0,
	      "76": 0,
	      "77": 0,
	      "78": 0,
	      "79": 0,
	      "80": 0,
	      "81": 0,
	      "82": 0,
	      "83": 0,
	      "84": 0,
	      "85": 0,
	      "86": 0,
	      "87": 0,
	      "88": 0,
	      "89": 0,
	      "90": 0,
	      "91": 0,
	      "92": 0,
	      "93": 0,
	      "94": 0,
	      "95": 0,
	      "96": 0,
	      "97": 0,
	      "98": 0,
	      "99": 0,
	      "100": 0,
	      "101": 0,
	      "102": 0,
	      "103": 0,
	      "104": 0,
	      "105": 0,
	      "106": 0,
	      "107": 0,
	      "108": 0,
	      "109": 0,
	      "110": 0,
	      "111": 0,
	      "112": 0,
	      "113": 0,
	      "114": 0,
	      "115": 0,
	      "116": 0,
	      "117": 0,
	      "118": 0,
	      "119": 0,
	      "120": 0,
	      "121": 0,
	      "122": 0,
	      "123": 0,
	      "124": 0,
	      "125": 0,
	      "126": 0,
	      "127": 0,
	      "128": 0,
	      "129": 0
	    },
	    f: {
	      "0": 0,
	      "1": 0,
	      "2": 0,
	      "3": 0,
	      "4": 0,
	      "5": 0,
	      "6": 0,
	      "7": 0,
	      "8": 0,
	      "9": 0,
	      "10": 0,
	      "11": 0,
	      "12": 0,
	      "13": 0,
	      "14": 0,
	      "15": 0,
	      "16": 0
	    },
	    b: {
	      "0": [0, 0],
	      "1": [0, 0],
	      "2": [0],
	      "3": [0, 0],
	      "4": [0, 0],
	      "5": [0, 0],
	      "6": [0, 0],
	      "7": [0, 0],
	      "8": [0, 0],
	      "9": [0, 0],
	      "10": [0, 0],
	      "11": [0, 0],
	      "12": [0, 0],
	      "13": [0, 0],
	      "14": [0, 0],
	      "15": [0, 0],
	      "16": [0, 0],
	      "17": [0, 0],
	      "18": [0, 0],
	      "19": [0, 0],
	      "20": [0, 0],
	      "21": [0, 0],
	      "22": [0, 0],
	      "23": [0, 0],
	      "24": [0, 0],
	      "25": [0, 0],
	      "26": [0, 0],
	      "27": [0, 0],
	      "28": [0, 0],
	      "29": [0, 0]
	    },
	    _coverageSchema: "1a1c01bbd47fc00a2c39e90264f33305004495a9",
	    hash: "b110675ff3cb80c47a4b25302fa674382f5a0f8c"
	  };
	  var coverage = global[gcv] || (global[gcv] = {});
	  if (!coverage[path] || coverage[path].hash !== hash) {
	    coverage[path] = coverageData;
	  }
	  var actualCoverage = coverage[path];
	  {
	    // @ts-ignore
	    cov_6v7qpwivx = function () {
	      return actualCoverage;
	    };
	  }
	  return actualCoverage;
	}
	cov_6v7qpwivx(); /**
	                 * @file quality-menu-button.js
	                 */
	const MenuButton = (cov_6v7qpwivx().s[0]++, videojs__default["default"].getComponent('MenuButton')); /**
	                                                                                 * Checks whether any of the QualityLevels in a QualityLevelList have resolution information
	                                                                                 *
	                                                                                 * @param {QualityLevelList} qualityLevelList
	                                                                                 *        The list of QualityLevels
	                                                                                 * @return {boolean}
	                                                                                 *         True if any levels have resolution information, false if none have
	                                                                                 * @function hasResolutionInfo
	                                                                                 */
	cov_6v7qpwivx().s[1]++;
	const hasResolutionInfo = function (qualityLevelList) {
	  cov_6v7qpwivx().f[0]++;
	  cov_6v7qpwivx().s[2]++;
	  return Array.from(qualityLevelList).some(level => {
	    cov_6v7qpwivx().f[1]++;
	    cov_6v7qpwivx().s[3]++;
	    return level.height;
	  });
	}; /**
	   * Determines the appropriate sub label for the given lines of resolution
	   *
	   * @param {number} lines
	   *        The horizontal lines of resolution
	   * @return {string}
	   *         sub label for given resolution
	   * @function getSubLabel
	   */
	cov_6v7qpwivx().s[4]++;
	const getSubLabel = function (lines) {
	  cov_6v7qpwivx().f[2]++;
	  cov_6v7qpwivx().s[5]++;
	  if (lines >= 2160) {
	    cov_6v7qpwivx().b[0][0]++;
	    cov_6v7qpwivx().s[6]++;
	    return '4K';
	  } else {
	    cov_6v7qpwivx().b[0][1]++;
	  }
	  cov_6v7qpwivx().s[7]++;
	  if (lines >= 720) {
	    cov_6v7qpwivx().b[1][0]++;
	    cov_6v7qpwivx().s[8]++;
	    return 'HD';
	  } else {
	    cov_6v7qpwivx().b[1][1]++;
	  }
	  cov_6v7qpwivx().s[9]++;
	  return '';
	}; /**
	   * The component for controlling the quality menu
	   *
	   * @extends MenuButton
	   * @class QualityMenuButton
	   */
	class QualityMenuButton extends MenuButton {
	  /**
	  * Creates a QualityMenuButton
	  *
	  * @param {Player|Object} player
	  *        Main Player
	  * @param {Object} [options]
	  *        Options for QualityMenuButton
	  */
	  constructor(player, options = (cov_6v7qpwivx().b[2][0]++, {})) {
	    cov_6v7qpwivx().f[3]++;
	    cov_6v7qpwivx().s[10]++;
	    super(player, options);
	    cov_6v7qpwivx().s[11]++;
	    this.el_.setAttribute('aria-label', this.localize('Quality Levels'));
	    cov_6v7qpwivx().s[12]++;
	    this.controlText('Quality Levels');
	    cov_6v7qpwivx().s[13]++;
	    if (!player.options().experimentalSvgIcons) {
	      cov_6v7qpwivx().b[3][0]++;
	      cov_6v7qpwivx().s[14]++;
	      this.$('.vjs-icon-placeholder').classList.add('vjs-icon-cog');
	    } else {
	      cov_6v7qpwivx().b[3][1]++;
	    }
	    cov_6v7qpwivx().s[15]++;
	    this.setIcon('cog');
	    cov_6v7qpwivx().s[16]++;
	    this.qualityLevels_ = player.qualityLevels();
	    cov_6v7qpwivx().s[17]++;
	    this.update = this.update.bind(this);
	    cov_6v7qpwivx().s[18]++;
	    this.hide = this.hide.bind(this);
	    cov_6v7qpwivx().s[19]++;
	    this.handleQualityChange_ = this.handleQualityChange_.bind(this);
	    cov_6v7qpwivx().s[20]++;
	    this.firstChangeHandler_ = this.firstChangeHandler_.bind(this);
	    cov_6v7qpwivx().s[21]++;
	    this.enableDefaultResolution_ = this.enableDefaultResolution_.bind(this);
	    cov_6v7qpwivx().s[22]++;
	    this.on(this.qualityLevels_, 'addqualitylevel', this.update);
	    cov_6v7qpwivx().s[23]++;
	    this.on(this.qualityLevels_, 'removequalitylevel', this.update);
	    cov_6v7qpwivx().s[24]++;
	    this.on(this.qualityLevels_, 'change', this.handleQualityChange_); // TODO: Remove this and the `defaultResolution` option once videojs/http-streaming supports comparable functionality
	    cov_6v7qpwivx().s[25]++;
	    this.one(this.qualityLevels_, 'change', this.firstChangeHandler_);
	    cov_6v7qpwivx().s[26]++;
	    player.on('adstart', this.hide);
	    cov_6v7qpwivx().s[27]++;
	    player.on(['adend', 'adtimeout'], this.update);
	    cov_6v7qpwivx().s[28]++;
	    this.update();
	    cov_6v7qpwivx().s[29]++;
	    this.on('dispose', () => {
	      cov_6v7qpwivx().f[4]++;
	      cov_6v7qpwivx().s[30]++;
	      this.off(this.qualityLevels_, 'addqualitylevel', this.update);
	      cov_6v7qpwivx().s[31]++;
	      this.off(this.qualityLevels_, 'removequalitylevel', this.update);
	      cov_6v7qpwivx().s[32]++;
	      this.off(this.qualityLevels_, 'change', this.handleQualityChange_);
	      cov_6v7qpwivx().s[33]++;
	      this.off(this.qualityLevels_, 'change', this.firstChangeHandler_);
	      cov_6v7qpwivx().s[34]++;
	      player.off('adstart', this.hide);
	      cov_6v7qpwivx().s[35]++;
	      player.off(['adend', 'adtimeout'], this.update);
	      cov_6v7qpwivx().s[36]++;
	      player.off('loadedmetadata', this.enableDefaultResolution_);
	    });
	  } /**
	    * Allow sub components to stack CSS class names
	    *
	    * @return {string}
	    *         The constructed class name
	    * @method buildWrapperCSSClass
	    */
	  buildWrapperCSSClass() {
	    cov_6v7qpwivx().f[5]++;
	    cov_6v7qpwivx().s[37]++;
	    return `vjs-quality-menu-wrapper ${super.buildWrapperCSSClass()}`;
	  } /**
	    * Allow sub components to stack CSS class names
	    *
	    * @return {string}
	    *         The constructed class name
	    * @method buildCSSClass
	    */
	  buildCSSClass() {
	    cov_6v7qpwivx().f[6]++;
	    cov_6v7qpwivx().s[38]++;
	    return `vjs-quality-menu-button ${super.buildCSSClass()}`;
	  } /**
	    * Create the list of menu items.
	    *
	    * @return {Array}
	    *         The list of menu items
	    * @method createItems
	    */
	  createItems() {
	    cov_6v7qpwivx().f[7]++;
	    const items = (cov_6v7qpwivx().s[39]++, []);
	    cov_6v7qpwivx().s[40]++;
	    if (!((cov_6v7qpwivx().b[5][0]++, this.qualityLevels_) && (cov_6v7qpwivx().b[5][1]++, this.qualityLevels_.length))) {
	      cov_6v7qpwivx().b[4][0]++;
	      cov_6v7qpwivx().s[41]++;
	      return items;
	    } else {
	      cov_6v7qpwivx().b[4][1]++;
	    }
	    let groups;
	    cov_6v7qpwivx().s[42]++;
	    if ((cov_6v7qpwivx().b[7][0]++, this.options_.useResolutionLabels) && (cov_6v7qpwivx().b[7][1]++, hasResolutionInfo(this.qualityLevels_))) {
	      cov_6v7qpwivx().b[6][0]++;
	      cov_6v7qpwivx().s[43]++;
	      groups = this.groupByResolution_();
	      cov_6v7qpwivx().s[44]++;
	      this.addClass('vjs-quality-menu-button-use-resolution');
	    } else {
	      cov_6v7qpwivx().b[6][1]++;
	      cov_6v7qpwivx().s[45]++;
	      groups = this.groupByBitrate_();
	      cov_6v7qpwivx().s[46]++;
	      this.removeClass('vjs-quality-menu-button-use-resolution');
	    } // if there is only 1 or 0 menu items, we should just return an empty list so
	    // the ui does not appear when there are no options. We consider 1 to be no options
	    // since Auto will have the same behavior as selecting the only other option,
	    // so it is as effective as not having any options.
	    cov_6v7qpwivx().s[47]++;
	    if (groups.length <= 1) {
	      cov_6v7qpwivx().b[8][0]++;
	      cov_6v7qpwivx().s[48]++;
	      return [];
	    } else {
	      cov_6v7qpwivx().b[8][1]++;
	    }
	    cov_6v7qpwivx().s[49]++;
	    groups.forEach(group => {
	      cov_6v7qpwivx().f[8]++;
	      cov_6v7qpwivx().s[50]++;
	      if (group.levels.length) {
	        cov_6v7qpwivx().b[9][0]++;
	        cov_6v7qpwivx().s[51]++;
	        group.selectable = true;
	        cov_6v7qpwivx().s[52]++;
	        items.push(new QualityMenuItem(this.player(), group));
	      } else {
	        cov_6v7qpwivx().b[9][1]++;
	      }
	    }); // Add the Auto menu item
	    const auto = (cov_6v7qpwivx().s[53]++, new QualityMenuItem(this.player(), {
	      levels: Array.prototype.map.call(this.qualityLevels_, (level, i) => {
	        cov_6v7qpwivx().f[9]++;
	        cov_6v7qpwivx().s[54]++;
	        return i;
	      }),
	      label: this.localize('Auto'),
	      controlText: '',
	      active: true,
	      selected: true,
	      selectable: true
	    }));
	    cov_6v7qpwivx().s[55]++;
	    this.autoMenuItem_ = auto;
	    cov_6v7qpwivx().s[56]++;
	    items.push(auto);
	    cov_6v7qpwivx().s[57]++;
	    return items;
	  } /**
	    * Group quality levels by lines of resolution
	    *
	    * @return {Array}
	    *         Array of each group
	    * @method groupByResolution_
	    */
	  groupByResolution_() {
	    cov_6v7qpwivx().f[10]++;
	    const groups = (cov_6v7qpwivx().s[58]++, {});
	    const order = (cov_6v7qpwivx().s[59]++, []);
	    cov_6v7qpwivx().s[60]++;
	    for (let i = (cov_6v7qpwivx().s[61]++, 0), l = (cov_6v7qpwivx().s[62]++, this.qualityLevels_.length); i < l; i++) {
	      const level = (cov_6v7qpwivx().s[63]++, this.qualityLevels_[i]);
	      const active = (cov_6v7qpwivx().s[64]++, this.qualityLevels_.selectedIndex === i);
	      const lines = (cov_6v7qpwivx().s[65]++, level.height); // Do not include an audio-only level
	      cov_6v7qpwivx().s[66]++;
	      if (!lines) {
	        cov_6v7qpwivx().b[10][0]++;
	        cov_6v7qpwivx().s[67]++;
	        continue;
	      } else {
	        cov_6v7qpwivx().b[10][1]++;
	      }
	      let label;
	      cov_6v7qpwivx().s[68]++;
	      if (this.options_.resolutionLabelBitrates) {
	        cov_6v7qpwivx().b[11][0]++;
	        const kbRate = (cov_6v7qpwivx().s[69]++, Math.round(level.bitrate / 1000));
	        cov_6v7qpwivx().s[70]++;
	        label = `${lines}p @ ${kbRate} kbps`;
	      } else {
	        cov_6v7qpwivx().b[11][1]++;
	        cov_6v7qpwivx().s[71]++;
	        label = lines + 'p';
	      }
	      cov_6v7qpwivx().s[72]++;
	      if (!groups[label]) {
	        cov_6v7qpwivx().b[12][0]++;
	        const subLabel = (cov_6v7qpwivx().s[73]++, getSubLabel(lines));
	        cov_6v7qpwivx().s[74]++;
	        groups[label] = {
	          levels: [],
	          label,
	          controlText: '',
	          subLabel
	        };
	        cov_6v7qpwivx().s[75]++;
	        order.push({
	          label,
	          lines
	        });
	      } else {
	        cov_6v7qpwivx().b[12][1]++;
	      }
	      cov_6v7qpwivx().s[76]++;
	      if (active) {
	        cov_6v7qpwivx().b[13][0]++;
	        cov_6v7qpwivx().s[77]++;
	        groups[label].active = true;
	      } else {
	        cov_6v7qpwivx().b[13][1]++;
	      }
	      cov_6v7qpwivx().s[78]++;
	      groups[label].levels.push(i);
	    } // Sort from High to Low
	    cov_6v7qpwivx().s[79]++;
	    order.sort((a, b) => {
	      cov_6v7qpwivx().f[11]++;
	      cov_6v7qpwivx().s[80]++;
	      return b.lines - a.lines;
	    });
	    const sortedGroups = (cov_6v7qpwivx().s[81]++, []);
	    cov_6v7qpwivx().s[82]++;
	    order.forEach(group => {
	      cov_6v7qpwivx().f[12]++;
	      cov_6v7qpwivx().s[83]++;
	      sortedGroups.push(groups[group.label]);
	    });
	    cov_6v7qpwivx().s[84]++;
	    return sortedGroups;
	  } /**
	    * Group quality levels by bitrate into SD and HD buckets
	    *
	    * @return {Array}
	    *         Array of each group
	    * @method groupByBitrate_
	    */
	  groupByBitrate_() {
	    cov_6v7qpwivx().f[13]++; // groups[0] for HD, groups[1] for SD, since we want sorting from high to low\
	    const groups = (cov_6v7qpwivx().s[85]++, [{
	      levels: [],
	      label: 'HD',
	      controlText: 'High Definition'
	    }, {
	      levels: [],
	      label: 'SD',
	      controlText: 'Standard Definition'
	    }]);
	    cov_6v7qpwivx().s[86]++;
	    for (let i = (cov_6v7qpwivx().s[87]++, 0), l = (cov_6v7qpwivx().s[88]++, this.qualityLevels_.length); i < l; i++) {
	      const level = (cov_6v7qpwivx().s[89]++, this.qualityLevels_[i]);
	      const active = (cov_6v7qpwivx().s[90]++, this.qualityLevels_.selectedIndex === i);
	      let group;
	      cov_6v7qpwivx().s[91]++;
	      if (level.bitrate < this.options_.sdBitrateLimit) {
	        cov_6v7qpwivx().b[14][0]++;
	        cov_6v7qpwivx().s[92]++;
	        group = groups[1];
	      } else {
	        cov_6v7qpwivx().b[14][1]++;
	        cov_6v7qpwivx().s[93]++;
	        group = groups[0];
	      }
	      cov_6v7qpwivx().s[94]++;
	      if (active) {
	        cov_6v7qpwivx().b[15][0]++;
	        cov_6v7qpwivx().s[95]++;
	        group.active = true;
	      } else {
	        cov_6v7qpwivx().b[15][1]++;
	      }
	      cov_6v7qpwivx().s[96]++;
	      group.levels.push(i);
	    }
	    cov_6v7qpwivx().s[97]++;
	    if ((cov_6v7qpwivx().b[17][0]++, !groups[0].levels.length) || (cov_6v7qpwivx().b[17][1]++, !groups[1].levels.length)) {
	      cov_6v7qpwivx().b[16][0]++;
	      cov_6v7qpwivx().s[98]++; // Either HD or SD do not have any quality levels, we should just return an empty
	      // list so the ui does not appear when there are no options. We consider 1
	      // to be no options since Auto will have the same behavior as selecting the only
	      // other option, so it is as effective as not having any options.
	      return [];
	    } else {
	      cov_6v7qpwivx().b[16][1]++;
	    }
	    cov_6v7qpwivx().s[99]++;
	    return groups;
	  } /**
	    * Handle a change event from the QualityLevelList
	    *
	    * @method handleQualityChange_
	    */
	  handleQualityChange_() {
	    cov_6v7qpwivx().f[14]++;
	    const selected = (cov_6v7qpwivx().s[100]++, this.qualityLevels_[this.qualityLevels_.selectedIndex]);
	    const useResolution = (cov_6v7qpwivx().s[101]++, (cov_6v7qpwivx().b[18][0]++, this.options_.useResolutionLabels) && (cov_6v7qpwivx().b[18][1]++, hasResolutionInfo(this.qualityLevels_)));
	    let subLabel = (cov_6v7qpwivx().s[102]++, '');
	    cov_6v7qpwivx().s[103]++;
	    if (selected) {
	      cov_6v7qpwivx().b[19][0]++;
	      cov_6v7qpwivx().s[104]++;
	      if (useResolution) {
	        cov_6v7qpwivx().b[20][0]++;
	        cov_6v7qpwivx().s[105]++;
	        subLabel = getSubLabel(selected.height);
	      } else {
	        cov_6v7qpwivx().b[20][1]++;
	        cov_6v7qpwivx().s[106]++;
	        if (selected.bitrate >= this.options_.sdBitrateLimit) {
	          cov_6v7qpwivx().b[21][0]++;
	          cov_6v7qpwivx().s[107]++;
	          subLabel = 'HD';
	        } else {
	          cov_6v7qpwivx().b[21][1]++;
	        }
	      }
	    } else {
	      cov_6v7qpwivx().b[19][1]++;
	    }
	    cov_6v7qpwivx().s[108]++;
	    if (subLabel === 'HD') {
	      cov_6v7qpwivx().b[22][0]++;
	      cov_6v7qpwivx().s[109]++;
	      this.addClass('vjs-quality-menu-button-HD-flag');
	      cov_6v7qpwivx().s[110]++;
	      this.removeClass('vjs-quality-menu-button-4K-flag');
	    } else {
	      cov_6v7qpwivx().b[22][1]++;
	      cov_6v7qpwivx().s[111]++;
	      if (subLabel === '4K') {
	        cov_6v7qpwivx().b[23][0]++;
	        cov_6v7qpwivx().s[112]++;
	        this.removeClass('vjs-quality-menu-button-HD-flag');
	        cov_6v7qpwivx().s[113]++;
	        this.addClass('vjs-quality-menu-button-4K-flag');
	      } else {
	        cov_6v7qpwivx().b[23][1]++;
	        cov_6v7qpwivx().s[114]++;
	        this.removeClass('vjs-quality-menu-button-HD-flag');
	        cov_6v7qpwivx().s[115]++;
	        this.removeClass('vjs-quality-menu-button-4K-flag');
	      }
	    }
	    cov_6v7qpwivx().s[116]++;
	    if (this.autoMenuItem_) {
	      cov_6v7qpwivx().b[24][0]++;
	      cov_6v7qpwivx().s[117]++;
	      if ((cov_6v7qpwivx().b[26][0]++, this.autoMenuItem_.manuallySelected_) && (cov_6v7qpwivx().b[26][1]++, selected)) {
	        cov_6v7qpwivx().b[25][0]++;
	        cov_6v7qpwivx().s[118]++; // auto mode, update the sub label
	        this.autoMenuItem_.subLabel_.innerHTML = this.localize(subLabel);
	      } else {
	        cov_6v7qpwivx().b[25][1]++;
	        cov_6v7qpwivx().s[119]++;
	        this.autoMenuItem_.subLabel_.innerHTML = '';
	      }
	    } else {
	      cov_6v7qpwivx().b[24][1]++;
	    }
	  } /**
	    * Enable the `defaultResolution`, if specified
	    *
	    * @method enableDefaultResolution_
	    */
	  enableDefaultResolution_() {
	    cov_6v7qpwivx().f[15]++;
	    const defaultResolution = (cov_6v7qpwivx().s[120]++, this.options_.defaultResolution);
	    cov_6v7qpwivx().s[121]++;
	    for (let i = (cov_6v7qpwivx().s[122]++, 0); i < this.items.length; i++) {
	      cov_6v7qpwivx().s[123]++;
	      if (this.items[i].options_.label.indexOf(defaultResolution) !== -1) {
	        cov_6v7qpwivx().b[27][0]++;
	        cov_6v7qpwivx().s[124]++;
	        this.items[i].handleClick();
	      } else {
	        cov_6v7qpwivx().b[27][1]++;
	      }
	    }
	  } /**
	    * Handle the first change event in order to enable the `defaultResolution`, if specified
	    *
	    * @method firstChangeHandler_
	    */
	  firstChangeHandler_() {
	    cov_6v7qpwivx().f[16]++;
	    cov_6v7qpwivx().s[125]++; // If we haven't loaded metadata yet and we expect it before playback is initiated, wait to perform the quality switch.
	    // Doing so before 'loadedmetadata' can result in cancelling requests for init segments required for the browser to fire 'loadedmetadata'.
	    if ((cov_6v7qpwivx().b[29][0]++, this.player_.readyState() < 1) && (cov_6v7qpwivx().b[29][1]++, this.player_.preload() !== 'none')) {
	      cov_6v7qpwivx().b[28][0]++;
	      cov_6v7qpwivx().s[126]++;
	      this.player_.one('loadedmetadata', this.enableDefaultResolution_);
	      cov_6v7qpwivx().s[127]++;
	      return;
	    } else {
	      cov_6v7qpwivx().b[28][1]++;
	    }
	    cov_6v7qpwivx().s[128]++;
	    this.enableDefaultResolution_();
	  }
	}
	cov_6v7qpwivx().s[129]++;
	videojs__default["default"].registerComponent('QualityMenuButton', QualityMenuButton);

	var version = "1.0.4";

	function cov_22p24cds2m() {
	  var path = "/Users/srimron-soutter/repos/github/videojs-contrib-quality-menu/src/plugin.js";
	  var hash = "c23db057a969e5836bc9a43a74c55031ad6d6af9";
	  var global = new Function("return this")();
	  var gcv = "__coverage__";
	  var coverageData = {
	    path: "/Users/srimron-soutter/repos/github/videojs-contrib-quality-menu/src/plugin.js",
	    statementMap: {
	      "0": {
	        start: {
	          line: 6,
	          column: 17
	        },
	        end: {
	          line: 11,
	          column: 1
	        }
	      },
	      "1": {
	        start: {
	          line: 27,
	          column: 22
	        },
	        end: {
	          line: 43,
	          column: 1
	        }
	      },
	      "2": {
	        start: {
	          line: 28,
	          column: 2
	        },
	        end: {
	          line: 28,
	          column: 38
	        }
	      },
	      "3": {
	        start: {
	          line: 30,
	          column: 21
	        },
	        end: {
	          line: 30,
	          column: 50
	        }
	      },
	      "4": {
	        start: {
	          line: 32,
	          column: 17
	        },
	        end: {
	          line: 36,
	          column: 3
	        }
	      },
	      "5": {
	        start: {
	          line: 38,
	          column: 2
	        },
	        end: {
	          line: 42,
	          column: 4
	        }
	      },
	      "6": {
	        start: {
	          line: 39,
	          column: 4
	        },
	        end: {
	          line: 39,
	          column: 43
	        }
	      },
	      "7": {
	        start: {
	          line: 40,
	          column: 4
	        },
	        end: {
	          line: 40,
	          column: 35
	        }
	      },
	      "8": {
	        start: {
	          line: 41,
	          column: 4
	        },
	        end: {
	          line: 41,
	          column: 21
	        }
	      },
	      "9": {
	        start: {
	          line: 52,
	          column: 19
	        },
	        end: {
	          line: 72,
	          column: 1
	        }
	      },
	      "10": {
	        start: {
	          line: 53,
	          column: 2
	        },
	        end: {
	          line: 71,
	          column: 3
	        }
	      },
	      "11": {
	        start: {
	          line: 55,
	          column: 4
	        },
	        end: {
	          line: 55,
	          column: 27
	        }
	      },
	      "12": {
	        start: {
	          line: 57,
	          column: 20
	        },
	        end: {
	          line: 57,
	          column: 28
	        }
	      },
	      "13": {
	        start: {
	          line: 59,
	          column: 4
	        },
	        end: {
	          line: 66,
	          column: 7
	        }
	      },
	      "14": {
	        start: {
	          line: 60,
	          column: 6
	        },
	        end: {
	          line: 60,
	          column: 49
	        }
	      },
	      "15": {
	        start: {
	          line: 62,
	          column: 6
	        },
	        end: {
	          line: 65,
	          column: 9
	        }
	      },
	      "16": {
	        start: {
	          line: 63,
	          column: 8
	        },
	        end: {
	          line: 63,
	          column: 20
	        }
	      },
	      "17": {
	        start: {
	          line: 64,
	          column: 8
	        },
	        end: {
	          line: 64,
	          column: 51
	        }
	      },
	      "18": {
	        start: {
	          line: 69,
	          column: 4
	        },
	        end: {
	          line: 69,
	          column: 34
	        }
	      },
	      "19": {
	        start: {
	          line: 70,
	          column: 4
	        },
	        end: {
	          line: 70,
	          column: 41
	        }
	      },
	      "20": {
	        start: {
	          line: 86,
	          column: 20
	        },
	        end: {
	          line: 88,
	          column: 1
	        }
	      },
	      "21": {
	        start: {
	          line: 87,
	          column: 2
	        },
	        end: {
	          line: 87,
	          column: 57
	        }
	      },
	      "22": {
	        start: {
	          line: 91,
	          column: 0
	        },
	        end: {
	          line: 91,
	          column: 51
	        }
	      },
	      "23": {
	        start: {
	          line: 94,
	          column: 0
	        },
	        end: {
	          line: 94,
	          column: 30
	        }
	      }
	    },
	    fnMap: {
	      "0": {
	        name: "(anonymous_0)",
	        decl: {
	          start: {
	            line: 27,
	            column: 22
	          },
	          end: {
	            line: 27,
	            column: 23
	          }
	        },
	        loc: {
	          start: {
	            line: 27,
	            column: 43
	          },
	          end: {
	            line: 43,
	            column: 1
	          }
	        },
	        line: 27
	      },
	      "1": {
	        name: "(anonymous_1)",
	        decl: {
	          start: {
	            line: 38,
	            column: 9
	          },
	          end: {
	            line: 38,
	            column: 10
	          }
	        },
	        loc: {
	          start: {
	            line: 38,
	            column: 20
	          },
	          end: {
	            line: 42,
	            column: 3
	          }
	        },
	        line: 38
	      },
	      "2": {
	        name: "(anonymous_2)",
	        decl: {
	          start: {
	            line: 52,
	            column: 19
	          },
	          end: {
	            line: 52,
	            column: 20
	          }
	        },
	        loc: {
	          start: {
	            line: 52,
	            column: 45
	          },
	          end: {
	            line: 72,
	            column: 1
	          }
	        },
	        line: 52
	      },
	      "3": {
	        name: "(anonymous_3)",
	        decl: {
	          start: {
	            line: 57,
	            column: 20
	          },
	          end: {
	            line: 57,
	            column: 21
	          }
	        },
	        loc: {
	          start: {
	            line: 57,
	            column: 26
	          },
	          end: {
	            line: 57,
	            column: 28
	          }
	        },
	        line: 57
	      },
	      "4": {
	        name: "(anonymous_4)",
	        decl: {
	          start: {
	            line: 59,
	            column: 17
	          },
	          end: {
	            line: 59,
	            column: 18
	          }
	        },
	        loc: {
	          start: {
	            line: 59,
	            column: 23
	          },
	          end: {
	            line: 66,
	            column: 5
	          }
	        },
	        line: 59
	      },
	      "5": {
	        name: "(anonymous_5)",
	        decl: {
	          start: {
	            line: 62,
	            column: 29
	          },
	          end: {
	            line: 62,
	            column: 30
	          }
	        },
	        loc: {
	          start: {
	            line: 62,
	            column: 35
	          },
	          end: {
	            line: 65,
	            column: 7
	          }
	        },
	        line: 62
	      },
	      "6": {
	        name: "(anonymous_6)",
	        decl: {
	          start: {
	            line: 69,
	            column: 25
	          },
	          end: {
	            line: 69,
	            column: 26
	          }
	        },
	        loc: {
	          start: {
	            line: 69,
	            column: 31
	          },
	          end: {
	            line: 69,
	            column: 33
	          }
	        },
	        line: 69
	      },
	      "7": {
	        name: "(anonymous_7)",
	        decl: {
	          start: {
	            line: 86,
	            column: 20
	          },
	          end: {
	            line: 86,
	            column: 21
	          }
	        },
	        loc: {
	          start: {
	            line: 86,
	            column: 38
	          },
	          end: {
	            line: 88,
	            column: 1
	          }
	        },
	        line: 86
	      }
	    },
	    branchMap: {
	      "0": {
	        loc: {
	          start: {
	            line: 53,
	            column: 2
	          },
	          end: {
	            line: 71,
	            column: 3
	          }
	        },
	        type: "if",
	        locations: [{
	          start: {
	            line: 53,
	            column: 2
	          },
	          end: {
	            line: 71,
	            column: 3
	          }
	        }, {
	          start: {
	            line: 53,
	            column: 2
	          },
	          end: {
	            line: 71,
	            column: 3
	          }
	        }],
	        line: 53
	      }
	    },
	    s: {
	      "0": 0,
	      "1": 0,
	      "2": 0,
	      "3": 0,
	      "4": 0,
	      "5": 0,
	      "6": 0,
	      "7": 0,
	      "8": 0,
	      "9": 0,
	      "10": 0,
	      "11": 0,
	      "12": 0,
	      "13": 0,
	      "14": 0,
	      "15": 0,
	      "16": 0,
	      "17": 0,
	      "18": 0,
	      "19": 0,
	      "20": 0,
	      "21": 0,
	      "22": 0,
	      "23": 0
	    },
	    f: {
	      "0": 0,
	      "1": 0,
	      "2": 0,
	      "3": 0,
	      "4": 0,
	      "5": 0,
	      "6": 0,
	      "7": 0
	    },
	    b: {
	      "0": [0, 0]
	    },
	    _coverageSchema: "1a1c01bbd47fc00a2c39e90264f33305004495a9",
	    hash: "c23db057a969e5836bc9a43a74c55031ad6d6af9"
	  };
	  var coverage = global[gcv] || (global[gcv] = {});
	  if (!coverage[path] || coverage[path].hash !== hash) {
	    coverage[path] = coverageData;
	  }
	  var actualCoverage = coverage[path];
	  {
	    // @ts-ignore
	    cov_22p24cds2m = function () {
	      return actualCoverage;
	    };
	  }
	  return actualCoverage;
	}
	cov_22p24cds2m();
	const defaults = (cov_22p24cds2m().s[0]++, {
	  sdBitrateLimit: 2000000,
	  useResolutionLabels: true,
	  resolutionLabelBitrates: false,
	  defaultResolution: 'none'
	}); /**
	    * Function to invoke when the player is ready.
	    *
	    * This is a great place for your plugin to initialize itself. When this
	    * function is called, the player will have its DOM and child components
	    * in place.
	    *
	    * @function onPlayerReady
	    * @param    {Player} player
	    *           A Video.js player.
	    * @param    {Object} [options={}]
	    *           An object of options left to the plugin author to define.
	    * @return {Function} disposal function for re initialization
	    */
	cov_22p24cds2m().s[1]++;
	const onPlayerReady = (player, options) => {
	  cov_22p24cds2m().f[0]++;
	  cov_22p24cds2m().s[2]++;
	  player.addClass('vjs-quality-menu');
	  const controlBar = (cov_22p24cds2m().s[3]++, player.getChild('controlBar'));
	  const button = (cov_22p24cds2m().s[4]++, controlBar.addChild('QualityMenuButton', options, controlBar.children_.length - 2));
	  cov_22p24cds2m().s[5]++;
	  return function () {
	    cov_22p24cds2m().f[1]++;
	    cov_22p24cds2m().s[6]++;
	    player.removeClass('vjs-quality-menu');
	    cov_22p24cds2m().s[7]++;
	    controlBar.removeChild(button);
	    cov_22p24cds2m().s[8]++;
	    button.dispose();
	  };
	}; /**
	   * Main entry point for the plugin
	   *
	   * @function initPlugin
	   * @param {Player} player a reference to a videojs Player instance
	   * @param {Object} [options] an object with plugin options
	   */
	cov_22p24cds2m().s[9]++;
	const initPlugin = function (player, options) {
	  cov_22p24cds2m().f[2]++;
	  cov_22p24cds2m().s[10]++;
	  if (typeof player.qualityLevels !== 'undefined') {
	    cov_22p24cds2m().b[0][0]++;
	    cov_22p24cds2m().s[11]++; // call qualityLevels to initialize it in case it hasnt been initialized yet
	    player.qualityLevels();
	    cov_22p24cds2m().s[12]++;
	    let disposeFn = () => {
	      cov_22p24cds2m().f[3]++;
	    };
	    cov_22p24cds2m().s[13]++;
	    player.ready(() => {
	      cov_22p24cds2m().f[4]++;
	      cov_22p24cds2m().s[14]++;
	      disposeFn = onPlayerReady(player, options);
	      cov_22p24cds2m().s[15]++;
	      player.on('loadstart', () => {
	        cov_22p24cds2m().f[5]++;
	        cov_22p24cds2m().s[16]++;
	        disposeFn();
	        cov_22p24cds2m().s[17]++;
	        disposeFn = onPlayerReady(player, options);
	      });
	    }); // reinitialization is no-op for now
	    cov_22p24cds2m().s[18]++;
	    player.qualityMenu = () => {
	      cov_22p24cds2m().f[6]++;
	    };
	    cov_22p24cds2m().s[19]++;
	    player.qualityMenu.VERSION = version;
	  } else {
	    cov_22p24cds2m().b[0][1]++;
	  }
	}; /**
	   * A video.js plugin.
	   *
	   * In the plugin function, the value of `this` is a video.js `Player`
	   * instance. You cannot rely on the player being in a "ready" state here,
	   * depending on how the plugin is invoked. This may or may not be important
	   * to you; if not, remove the wait for "ready"!
	   *
	   * @function qualityMenu
	   * @param    {Object} [options={}]
	   *           An object of options left to the plugin author to define.
	   */
	cov_22p24cds2m().s[20]++;
	const qualityMenu = function (options) {
	  cov_22p24cds2m().f[7]++;
	  cov_22p24cds2m().s[21]++;
	  initPlugin(this, videojs__default["default"].obj.merge(defaults, options));
	}; // Register the plugin with video.js.
	cov_22p24cds2m().s[22]++;
	videojs__default["default"].registerPlugin('qualityMenu', qualityMenu); // Include the version number.
	cov_22p24cds2m().s[23]++;
	qualityMenu.VERSION = version;

	const Player = videojs__default["default"].getComponent('Player');
	QUnit__default["default"].test('the environment is sane', function (assert) {
	  assert.strictEqual(typeof Array.isArray, 'function', 'es5 exists');
	  assert.strictEqual(typeof sinon__default["default"], 'object', 'sinon exists');
	  assert.strictEqual(typeof videojs__default["default"], 'function', 'videojs exists');
	  assert.strictEqual(typeof qualityMenu, 'function', 'plugin is a function');
	});
	QUnit__default["default"].module('videojs-contrib-quality-menu', {
	  beforeEach() {
	    // Mock the environment's timers because certain things - particularly
	    // player readiness - are asynchronous in video.js 5. This MUST come
	    // before any player is created; otherwise, timers could get created
	    // with the actual timer methods!
	    this.clock = sinon__default["default"].useFakeTimers();
	    this.fixture = document_1.getElementById('qunit-fixture');
	    this.video = document_1.createElement('video');
	    this.fixture.appendChild(this.video);
	    this.player = videojs__default["default"](this.video);
	  },
	  afterEach() {
	    this.player.dispose();
	    this.clock.restore();
	  }
	});
	QUnit__default["default"].test('registers itself with video.js', function (assert) {
	  assert.expect(2);
	  assert.strictEqual(typeof Player.prototype.qualityMenu, 'function', 'videojs-contrib-quality-menu plugin was registered');
	  this.player.qualityMenu();

	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  assert.ok(this.player.hasClass('vjs-quality-menu'), 'the plugin adds a class to the player');
	});
	QUnit__default["default"].test('Hides and shows the component when an ad plays', function (assert) {
	  this.player.qualityMenu();

	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  assert.equal(button.items.length, 0, 'no menu items when empty quality level list');

	  // add multiple quality levels so button displays.
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 2000001,
	    width: 500,
	    height: 500,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 3000001,
	    width: 600,
	    height: 600,
	    enabled: () => {}
	  });
	  assert.notOk(button.hasClass('vjs-hidden'), 'the plugin is displayed');
	  this.player.trigger('adstart');
	  assert.ok(button.hasClass('vjs-hidden'), 'the plugin is hidden');
	  this.player.trigger('adend');
	  assert.notOk(button.hasClass('vjs-hidden'), 'the plugin is no longer hidden');
	});
	QUnit__default["default"].test('Groups QualityLevels by bitrate when useResolutionLabels is false', function (assert) {
	  this.player.qualityMenu({
	    useResolutionLabels: false
	  });
	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  assert.equal(button.items.length, 0, 'no menu items when empty quality level list');
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 2000001,
	    width: 500,
	    height: 500,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 3000001,
	    width: 600,
	    height: 600,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '3',
	    bandwidth: 19999,
	    width: 300,
	    height: 300,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '4',
	    bandwidth: 1111,
	    width: 200,
	    height: 200,
	    enabled: () => {}
	  });
	  assert.equal(button.items.length, 3, 'created 3 menu items');
	  assert.equal(button.items[0].controlText(), 'High Definition', 'HD element');
	  assert.deepEqual(button.items[0].levels_, [0, 1], 'HD variants added to HD menu item');
	  assert.equal(button.items[1].controlText(), 'Standard Definition', 'SD element');
	  assert.deepEqual(button.items[1].levels_, [2, 3], 'SD variants added to SD menu item');
	  assert.equal(button.items[2].options_.label, 'Auto', 'Auto element');
	  assert.deepEqual(button.items[2].levels_, [0, 1, 2, 3], 'all variants added to Auto menu item');
	});
	QUnit__default["default"].test('Groups QualityLevels by resolution by default', function (assert) {
	  this.player.qualityMenu();
	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  assert.equal(button.items.length, 0, 'no menu items when empty quality level list');
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 2000001,
	    width: 500,
	    height: 500,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 3000001,
	    width: 600,
	    height: 600,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '3',
	    bandwidth: 19999,
	    width: 300,
	    height: 300,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '4',
	    bandwidth: 1111,
	    width: 200,
	    height: 200,
	    enabled: () => {}
	  });
	  assert.equal(button.items.length, 5, 'created 5 menu items');
	  assert.equal(button.items[0].options_.label, '600p', '600p');
	  assert.deepEqual(button.items[0].levels_, [1], '600p variants added to 600p menu item');
	  assert.equal(button.items[1].options_.label, '500p', '500p');
	  assert.deepEqual(button.items[1].levels_, [0], '500p variants added to 500p menu item');
	  assert.equal(button.items[2].options_.label, '300p', '300p');
	  assert.deepEqual(button.items[2].levels_, [2], '300p variants added to 300p menu item');
	  assert.equal(button.items[3].options_.label, '200p', '200p');
	  assert.deepEqual(button.items[3].levels_, [3], '200p variants added to 200p menu item');
	  assert.equal(button.items[4].options_.label, 'Auto', 'Auto');
	  assert.deepEqual(button.items[4].levels_, [0, 1, 2, 3], 'Auto variants added to Auto menu item');
	});
	QUnit__default["default"].test('Does not create menu items for audio-only levels', function (assert) {
	  this.player.qualityMenu();
	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  assert.equal(button.items.length, 0, 'no menu items when empty quality level list');
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 2000001,
	    width: 500,
	    height: 500,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 3000001,
	    width: 600,
	    height: 600,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '3',
	    bandwidth: 19999,
	    width: 300,
	    height: 300,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '4',
	    bandwidth: 1111,
	    width: undefined,
	    height: undefined,
	    enabled: () => {}
	  });
	  assert.equal(button.items.length, 4, 'created 4 menu items');
	  assert.equal(button.items[0].options_.label, '600p', '600p');
	  assert.deepEqual(button.items[0].levels_, [1], '600p variants added to 600p menu item');
	  assert.equal(button.items[1].options_.label, '500p', '500p');
	  assert.deepEqual(button.items[1].levels_, [0], '500p variants added to 500p menu item');
	  assert.equal(button.items[2].options_.label, '300p', '300p');
	  assert.deepEqual(button.items[2].levels_, [2], '300p variants added to 300p menu item');
	  assert.equal(button.items[3].options_.label, 'Auto', 'Auto');
	  assert.deepEqual(button.items[3].levels_, [0, 1, 2, 3], 'All variants added to Auto menu item');
	});
	QUnit__default["default"].test('Dispalys bitrate along with resolution when resolutionLabelBitrates option', function (assert) {
	  this.player.qualityMenu({
	    resolutionLabelBitrates: true
	  });
	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  assert.equal(button.items.length, 0, 'no menu items when empty quality level list');
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 2000001,
	    width: 500,
	    height: 500,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 3000001,
	    width: 600,
	    height: 600,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '3',
	    bandwidth: 19999,
	    width: 300,
	    height: 300,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '4',
	    bandwidth: 1111,
	    width: 200,
	    height: 200,
	    enabled: () => {}
	  });
	  assert.equal(button.items.length, 5, 'created 5 menu items');
	  assert.equal(button.items[0].options_.label, '600p @ 3000 kbps', '600p @ 3000 kbps');
	  assert.deepEqual(button.items[0].levels_, [1], '600p variants added to 600p menu item');
	  assert.equal(button.items[1].options_.label, '500p @ 2000 kbps', '500p @ 2000 kbps');
	  assert.deepEqual(button.items[1].levels_, [0], '500p variants added to 500p menu item');
	  assert.equal(button.items[2].options_.label, '300p @ 20 kbps', '300p @ 20 kbps');
	  assert.deepEqual(button.items[2].levels_, [2], '300p variants added to 300p menu item');
	  assert.equal(button.items[3].options_.label, '200p @ 1 kbps', '200p @ 1 kbps');
	  assert.deepEqual(button.items[3].levels_, [3], '200p variants added to 200p menu item');
	  assert.equal(button.items[4].options_.label, 'Auto', 'Auto');
	  assert.deepEqual(button.items[4].levels_, [0, 1, 2, 3], 'Auto variants added to Auto menu item');
	});
	QUnit__default["default"].test('Falls back to grouping by bitrate when no resolution info is available', function (assert) {
	  this.player.qualityMenu({
	    useResolutionLabels: true
	  });
	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  assert.equal(button.items.length, 0, 'no menu items when empty quality level list');
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 2000001,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 3000001,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '3',
	    bandwidth: 19999,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '4',
	    bandwidth: 1111,
	    enabled: () => {}
	  });
	  assert.equal(button.items.length, 3, 'created 3 menu items');
	  assert.equal(button.items[0].controlText(), 'High Definition', 'HD');
	  assert.deepEqual(button.items[0].levels_, [0, 1], 'HD variants added to HD menu item');
	  assert.equal(button.items[1].controlText(), 'Standard Definition', 'SD');
	  assert.deepEqual(button.items[1].levels_, [2, 3], 'SD variants added to SD menu item');
	  assert.equal(button.items[2].options_.label, 'Auto', 'Auto');
	  assert.deepEqual(button.items[2].levels_, [0, 1, 2, 3], 'Auto variants added to Auto menu item');
	});
	QUnit__default["default"].test('Attaches HD flag when an HD rendition is selected', function (assert) {
	  this.player.qualityMenu({
	    sdBitrateLimit: 100,
	    useResolutionLabels: false
	  });
	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  const menuContentElement = button.el().getElementsByTagName('ul')[0];
	  assert.equal(menuContentElement.children.length, 0, 'no menu items when empty quality level list');
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 101,
	    width: 500,
	    height: 500,
	    enabled: () => {}
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 99,
	    width: 100,
	    height: 100,
	    enabled: () => {}
	  });
	  assert.ok(!button.hasClass('vjs-quality-menu-button-HD-flag'), 'no hd flag when hd not selected');
	  levels.selectedIndex_ = 0;
	  videojs__default["default"].trigger(levels, 'change');
	  assert.ok(button.hasClass('vjs-quality-menu-button-HD-flag'), 'added hd flag');
	  levels.selectedIndex_ = 1;
	  videojs__default["default"].trigger(levels, 'change');
	  assert.ok(!button.hasClass('vjs-quality-menu-button-HD-flag'), 'no hd flag when hd not selected');
	});
	QUnit__default["default"].test('Passing default quality level reverts to auto if desired level is not found', function (assert) {
	  this.player.qualityMenu({
	    sdBitrateLimit: 100,
	    useResolutionLabels: false,
	    defaultResolution: 'HQ'
	  });
	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  const enabled = [true, true, true, true];
	  levels.addQualityLevel({
	    id: '0',
	    bandwidth: 102,
	    width: 500,
	    height: 500,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[0];
	      }
	      enabled[0] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 101,
	    width: 400,
	    height: 400,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[1];
	      }
	      enabled[1] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 99,
	    width: 200,
	    height: 200,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[2];
	      }
	      enabled[2] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '3',
	    bandwidth: 98,
	    width: 100,
	    height: 100,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[3];
	      }
	      enabled[3] = enable;
	      return enable;
	    }
	  });
	  const items = button.items;
	  assert.equal(items.length, 3, '3 menu items, auto, sd, hd');
	  levels.selectedIndex_ = 2;
	  videojs__default["default"].trigger(levels, 'change');
	  assert.ok(!items[1].hasClass('vjs-selected'), 'sd menu item not selected');
	  assert.ok(items[items.length - 1].hasClass('vjs-selected'), 'auto menu item selected');
	  assert.ok(!items[0].hasClass('vjs-selected'), 'hd menu item not selected');
	  assert.ok(levels[0].enabled, 'all levels enabled');
	  assert.ok(levels[1].enabled, 'all levels enabled');
	  assert.ok(levels[2].enabled, 'all levels enabled');
	  assert.ok(levels[3].enabled, 'all levels enabled');
	});
	QUnit__default["default"].test('Passing default "HD/SD" quality level selects correct starting level', function (assert) {
	  this.player.qualityMenu({
	    sdBitrateLimit: 100,
	    useResolutionLabels: false,
	    defaultResolution: 'HD'
	  });
	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  const enabled = [true, true, true, true];
	  levels.addQualityLevel({
	    id: '0',
	    bandwidth: 102,
	    width: 500,
	    height: 500,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[0];
	      }
	      enabled[0] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 101,
	    width: 400,
	    height: 400,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[1];
	      }
	      enabled[1] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 99,
	    width: 200,
	    height: 200,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[2];
	      }
	      enabled[2] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '3',
	    bandwidth: 98,
	    width: 100,
	    height: 100,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[3];
	      }
	      enabled[3] = enable;
	      return enable;
	    }
	  });
	  levels.selectedIndex_ = 3;
	  this.player.readyState = () => 1;
	  videojs__default["default"].trigger(levels, 'change');
	  const items = button.items;
	  assert.equal(items.length, 3, '3 menu items, auto, sd, hd');
	  assert.ok(items[0].hasClass('vjs-selected'), 'HD menu item selected at start');
	  assert.equal(items[0].options_.label, 'HD', 'HD menu item selected at start');
	  assert.ok(levels[0].enabled, 'HD levels enabled');
	  assert.ok(levels[1].enabled, 'HD levels enabled');
	  assert.ok(!levels[2].enabled, 'SD levels not enabled');
	  assert.ok(!levels[3].enabled, 'auto levels not enabled');
	});
	QUnit__default["default"].test('Passing default resolution quality level selects correct starting level', function (assert) {
	  this.player.qualityMenu({
	    sdBitrateLimit: 100,
	    useResolutionLabels: true,
	    defaultResolution: '500'
	  });
	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  const enabled = [true, true, true, true];
	  levels.addQualityLevel({
	    id: '0',
	    bandwidth: 102,
	    width: 500,
	    height: 500,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[0];
	      }
	      enabled[0] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 101,
	    width: 400,
	    height: 400,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[1];
	      }
	      enabled[1] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 99,
	    width: 200,
	    height: 200,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[2];
	      }
	      enabled[2] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '3',
	    bandwidth: 98,
	    width: 100,
	    height: 100,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[3];
	      }
	      enabled[3] = enable;
	      return enable;
	    }
	  });
	  levels.selectedIndex_ = 3;
	  this.player.readyState = () => 1;
	  videojs__default["default"].trigger(levels, 'change');
	  const items = button.items;
	  assert.equal(items.length, 5, '5 menu items, auto, 100, 200, 400, 500');
	  assert.ok(items[0].hasClass('vjs-selected'), 'HD menu item selected at start');
	  assert.equal(items[0].options_.label, '500p', 'HD menu item selected at start');
	  assert.ok(levels[0].enabled, '500p levels enabled');
	  assert.ok(!levels[1].enabled, '400p levels not enabled');
	  assert.ok(!levels[2].enabled, '200p levels not enabled');
	  assert.ok(!levels[3].enabled, '100p levels not enabled');
	});
	QUnit__default["default"].test('Clicking menu item calls quality level enabled functions', function (assert) {
	  this.player.qualityMenu({
	    sdBitrateLimit: 100,
	    useResolutionLabels: false
	  });
	  // Tick the clock forward enough to trigger the player to be "ready".
	  this.clock.tick(1);
	  const levels = this.player.qualityLevels();
	  const button = this.player.getChild('controlBar').getChild('QualityMenuButton');
	  const enabled = [true, true, true, true];
	  levels.addQualityLevel({
	    id: '0',
	    bandwidth: 102,
	    width: 500,
	    height: 500,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[0];
	      }
	      enabled[0] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '1',
	    bandwidth: 101,
	    width: 400,
	    height: 400,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[1];
	      }
	      enabled[1] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '2',
	    bandwidth: 99,
	    width: 200,
	    height: 200,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[2];
	      }
	      enabled[2] = enable;
	      return enable;
	    }
	  });
	  levels.addQualityLevel({
	    id: '3',
	    bandwidth: 98,
	    width: 100,
	    height: 100,
	    enabled: enable => {
	      if (typeof enable === 'undefined') {
	        return enabled[3];
	      }
	      enabled[3] = enable;
	      return enable;
	    }
	  });
	  levels.selectedIndex_ = 3;
	  videojs__default["default"].trigger(levels, 'change');
	  const items = button.items;
	  assert.equal(items.length, 3, '3 menu items, auto, sd, hd');
	  assert.ok(items[items.length - 1].hasClass('vjs-selected'), 'auto menu item selected at start');
	  assert.equal(items[items.length - 1].options_.label, 'Auto', 'auto menu item selected at start');

	  // click hd menu item
	  videojs__default["default"].trigger(items[0].el(), 'click');
	  levels.selectedIndex_ = 0;
	  videojs__default["default"].trigger(levels, 'change');
	  assert.ok(!items[items.length - 1].hasClass('vjs-selected'), 'auto menu item no longer selected');
	  assert.ok(!items[1].hasClass('vjs-selected'), 'sd menu item not selected');
	  assert.ok(items[0].hasClass('vjs-selected'), 'hd menu item selected');
	  assert.ok(levels[0].enabled, 'hd level is enabled');
	  assert.ok(levels[1].enabled, 'hd level is enabled');
	  assert.ok(!levels[2].enabled, 'non hd level disabled');
	  assert.ok(!levels[3].enabled, 'non hd level disabled');

	  // click sd menu item
	  videojs__default["default"].trigger(items[1].el(), 'click');
	  levels.selectedIndex_ = 3;
	  videojs__default["default"].trigger(levels, 'change');
	  assert.ok(items[1].hasClass('vjs-selected'), 'sd menu item selected');
	  assert.ok(!items[items.length - 1].hasClass('vjs-selected'), 'auto menu item not selected');
	  assert.ok(!items[0].hasClass('vjs-selected'), 'hd menu item not selected');
	  assert.ok(levels[2].enabled, 'sd level is enabled');
	  assert.ok(levels[3].enabled, 'sd level is enabled');
	  assert.ok(!levels[0].enabled, 'non sd level disabled');
	  assert.ok(!levels[1].enabled, 'non sd level disabled');

	  // click auto menu item
	  videojs__default["default"].trigger(items[items.length - 1].el(), 'click');
	  levels.selectedIndex_ = 2;
	  videojs__default["default"].trigger(levels, 'change');
	  assert.ok(!items[1].hasClass('vjs-selected'), 'sd menu item not selected');
	  assert.ok(items[items.length - 1].hasClass('vjs-selected'), 'auto menu item selected');
	  assert.ok(!items[0].hasClass('vjs-selected'), 'hd menu item not selected');
	  assert.ok(levels[0].enabled, 'all levels enabled');
	  assert.ok(levels[1].enabled, 'all levels enabled');
	  assert.ok(levels[2].enabled, 'all levels enabled');
	  assert.ok(levels[3].enabled, 'all levels enabled');
	});

})(QUnit, sinon, videojs);
