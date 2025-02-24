/*
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Internal dependencies
 */
import {
  findMatchingColor,
  findMatchingStylePreset,
  getPanelInitialHeight,
  getShapePresets,
  getTextPresets,
} from '../utils';
import { BACKGROUND_TEXT_MODE } from '../../../../../constants';
import objectWithout from '../../../../../utils/objectWithout';
import { TEXT_ELEMENT_DEFAULT_FONT } from '../../../../../app/font/defaultFonts';
import { PRESET_TYPES } from '../constants';

describe('Panels/StylePreset/utils', () => {
  const TEST_COLOR = {
    color: {
      r: 1,
      g: 1,
      b: 1,
    },
  };
  const TEST_COLOR_2 = {
    color: {
      r: 2,
      g: 2,
      b: 2,
    },
  };
  const STYLE_PRESET = {
    color: TEST_COLOR_2,
    backgroundTextMode: BACKGROUND_TEXT_MODE.FILL,
    backgroundColor: TEST_COLOR,
    font: TEXT_ELEMENT_DEFAULT_FONT,
  };
  it('should return matching color object', () => {
    const globalStoryStyles = {
      colors: [
        TEST_COLOR,
        {
          color: {
            r: 2,
            g: 2,
            b: 1,
          },
        },
      ],
    };
    const color = {
      color: {
        r: 1,
        g: 1,
        b: 1,
      },
    };
    expect(findMatchingColor(color, globalStoryStyles, true)).toStrictEqual(
      color
    );
  });

  it('should return undefined when not finding matching color', () => {
    const globalStoryStyles = {
      colors: [
        {
          color: {
            r: 1,
            g: 2,
            b: 3,
          },
        },
        {
          color: {
            r: 2,
            g: 2,
            b: 1,
          },
        },
      ],
    };
    expect(
      findMatchingColor(TEST_COLOR, globalStoryStyles, true)
    ).toBeUndefined();
  });

  it('should return matching text style preset', () => {
    const globalStoryStyles = {
      textStyles: [STYLE_PRESET],
    };
    const stylePreset = {
      color: {
        color: {
          r: 2,
          g: 2,
          b: 2,
        },
      },
      backgroundTextMode: BACKGROUND_TEXT_MODE.FILL,
      backgroundColor: {
        color: {
          r: 1,
          g: 1,
          b: 1,
        },
      },
      font: TEXT_ELEMENT_DEFAULT_FONT,
    };
    expect(
      findMatchingStylePreset(stylePreset, globalStoryStyles)
    ).toStrictEqual(stylePreset);
  });

  it('should return not return non-matching text style preset', () => {
    const globalStoryStyles = {
      textStyles: [STYLE_PRESET],
    };
    const stylePreset = {
      color: {
        color: {
          r: 1,
          g: 2,
          b: 2,
        },
      },
      backgroundTextMode: BACKGROUND_TEXT_MODE.FILL,
      backgroundColor: {
        color: {
          r: 1,
          g: 1,
          b: 1,
        },
      },
      font: TEXT_ELEMENT_DEFAULT_FONT,
    };
    expect(
      findMatchingStylePreset(stylePreset, globalStoryStyles)
    ).toBeUndefined();
  });

  it('should get correct text style presets from selected elements', () => {
    const stylePreset = {
      ...STYLE_PRESET,
      font: {
        family: 'Foo',
        fallbacks: ['Bar'],
      },
    };
    const elements = [
      {
        type: 'text',
        backgroundTextMode: BACKGROUND_TEXT_MODE.NONE,
        font: TEXT_ELEMENT_DEFAULT_FONT,
        foo: 'bar',
        padding: {
          vertical: 0,
          horizontal: 0,
        },
        content: '<span style="color: rgb(1,1,1)">Content</span>',
      },
      {
        type: 'text',
        x: 30,
        content:
          '<span style="font-weight: 700; font-style: italic; color: rgb(2,2,2)">Content</span>',
        ...objectWithout(stylePreset, ['color']),
      },
    ];
    const globalStoryStyles = {
      textStyles: [],
      colors: [],
    };
    const expected = {
      colors: [],
      textStyles: [
        {
          backgroundTextMode: 'NONE',
          color: TEST_COLOR,
          font: TEXT_ELEMENT_DEFAULT_FONT,
          fontWeight: 400,
          isItalic: false,
          isUnderline: false,
          letterSpacing: 0,
          padding: {
            horizontal: 0,
            vertical: 0,
          },
        },
        {
          backgroundColor: TEST_COLOR,
          backgroundTextMode: 'FILL',
          color: TEST_COLOR_2,
          font: {
            fallbacks: ['Bar'],
            family: 'Foo',
          },
          fontWeight: 700,
          isItalic: true,
          isUnderline: false,
          letterSpacing: 0,
        },
      ],
    };
    const presets = getTextPresets(
      elements,
      globalStoryStyles,
      PRESET_TYPES.STYLE
    );
    expect(presets).toStrictEqual(expected);
  });

  it('should ignore text color presets for multi-color text fields', () => {
    const elements = [
      {
        type: 'text',
        backgroundTextMode: BACKGROUND_TEXT_MODE.NONE,
        font: TEXT_ELEMENT_DEFAULT_FONT,
        content:
          '<span style="color: rgb(1,1,1)">O</span><span style="color: rgb(2,1,1)">K</span>',
      },
    ];
    const globalStoryStyles = {
      textStyles: [],
      colors: [],
    };
    const expected = {
      colors: [],
      textStyles: [],
    };
    const presets = getTextPresets(
      elements,
      globalStoryStyles,
      PRESET_TYPES.COLOR
    );
    expect(presets).toStrictEqual(expected);
  });

  it('should use black color when adding text style preset for multi-color text fields', () => {
    const stylePreset = {
      ...STYLE_PRESET,
      fontWeight: 400,
      isItalic: false,
      isUnderline: false,
      letterSpacing: 0,
      font: {
        family: 'Foo',
        fallbacks: ['Bar'],
      },
    };
    const elements = [
      {
        type: 'text',
        x: 30,
        content:
          '<span style="color: rgb(1,1,1)">O</span><span style="color: rgb(2,1,1)">K</span>',
        ...objectWithout(stylePreset, ['color']),
      },
    ];
    const globalStoryStyles = {
      textStyles: [],
      fillColors: [],
    };
    const expected = {
      colors: [],
      textStyles: [
        {
          ...stylePreset,
          color: { color: { r: 0, g: 0, b: 0 } },
        },
      ],
    };
    const presets = getTextPresets(
      elements,
      globalStoryStyles,
      PRESET_TYPES.STYLE
    );
    expect(presets).toStrictEqual(expected);
  });

  it('should get correct font weight for text preset', () => {
    const elements = [
      {
        type: 'text',
        backgroundTextMode: BACKGROUND_TEXT_MODE.NONE,
        font: TEXT_ELEMENT_DEFAULT_FONT,
        content: '<span style="font-weight: 600">Semi-bold</span>',
      },
    ];
    const globalStoryStyles = {
      textStyles: [],
      colors: [],
    };
    const expected = {
      colors: [],
      textStyles: [
        {
          backgroundTextMode: BACKGROUND_TEXT_MODE.NONE,
          font: TEXT_ELEMENT_DEFAULT_FONT,
          color: { color: { r: 0, g: 0, b: 0 } },
          fontWeight: 600,
          isItalic: false,
          isUnderline: false,
          letterSpacing: 0,
        },
      ],
    };
    const presets = getTextPresets(
      elements,
      globalStoryStyles,
      PRESET_TYPES.STYLE
    );
    expect(presets).toStrictEqual(expected);
  });

  it('should default to null/false when adding text style preset for mixed inline styles', () => {
    const stylePreset = {
      ...STYLE_PRESET,
      fontWeight: null,
      isItalic: false,
      isUnderline: false,
      letterSpacing: null,
      font: {
        family: 'Foo',
        fallbacks: ['Bar'],
      },
    };
    const elements = [
      {
        type: 'text',
        x: 30,
        content:
          '<span style="letter-spacing: 2px; font-style: italic; font-weight: 700; text-decoration: underline; color: rgb(1,1,1)">O</span><span style="text-decoration: none; color: rgb(2,1,1)">K</span>',
        ...objectWithout(stylePreset, ['color']),
      },
    ];
    const globalStoryStyles = {
      textStyles: [],
      fillColors: [],
    };
    const expected = {
      colors: [],
      textStyles: [
        {
          ...stylePreset,
          color: { color: { r: 0, g: 0, b: 0 } },
        },
      ],
    };
    const presets = getTextPresets(
      elements,
      globalStoryStyles,
      PRESET_TYPES.STYLE
    );
    expect(presets).toStrictEqual(expected);
  });

  it('should not consider existing presets as new', () => {
    const stylePreset = {
      ...STYLE_PRESET,
      font: {
        family: 'Foo',
        fallbacks: ['Bar'],
      },
    };
    const elements = [
      {
        type: 'text',
        backgroundTextMode: BACKGROUND_TEXT_MODE.NONE,
        font: TEXT_ELEMENT_DEFAULT_FONT,
        foo: 'bar',
        content: '<span style="color: rgb(1,1,1)">Content</span>',
        padding: {
          vertical: 0,
          horizontal: 0,
        },
      },
      {
        type: 'text',
        x: 30,
        content: '<span style="color: rgb(2,2,2)">Content</span>',
        ...objectWithout(stylePreset, ['color']),
      },
    ];
    const colorPresets = {
      colors: [TEST_COLOR, TEST_COLOR_2],
    };
    const expected = {
      colors: [],
      textStyles: [],
    };
    const presets = getTextPresets(elements, colorPresets, PRESET_TYPES.COLOR);
    expect(presets).toStrictEqual(expected);
  });

  it('should get correct shape presets from selected elements', () => {
    const elements = [
      {
        type: 'shape',
        backgroundColor: TEST_COLOR,
      },
      {
        type: 'shape',
        backgroundColor: TEST_COLOR_2,
      },
    ];
    const globalStoryStyles = {
      textStyles: [],
      colors: [],
    };
    const expected = {
      colors: [TEST_COLOR, TEST_COLOR_2],
    };
    const presets = getShapePresets(elements, globalStoryStyles);
    expect(presets).toStrictEqual(expected);
  });

  describe('getPanelInitialHeight', () => {
    it('should get the initial height correctly for color presets', () => {
      const presets = [
        { color: { r: 25, g: 39, b: 1 } },
        { color: { r: 25, g: 39, b: 2 } },
        { color: { r: 25, g: 39, b: 3 } },
        { color: { r: 25, g: 39, b: 4 } },
        { color: { r: 25, g: 39, b: 5 } },
        { color: { r: 25, g: 39, b: 6 } },
        { color: { r: 25, g: 39, b: 7 } },
        { color: { r: 25, g: 39, b: 8 } },
        { color: { r: 25, g: 39, b: 9 } },
        { color: { r: 25, g: 39, b: 10 } },
        { color: { r: 25, g: 39, b: 11 } },
        { color: { r: 25, g: 39, b: 12 } },
        { color: { r: 25, g: 39, b: 13 } },
      ];
      // Three rows.
      expect(getPanelInitialHeight(true, presets)).toBe(96);
      // One row.
      expect(
        getPanelInitialHeight(true, [{ color: { r: 196, g: 196, b: 196 } }])
      ).toBe(48);

      // No rows.
      expect(getPanelInitialHeight(true, [])).toBe(140);
    });

    it('should get the initial height correctly for style presets', () => {
      const presets = [
        { fontSize: 1 },
        { fontSize: 2 },
        { fontSize: 3 },
        { fontSize: 4 },
        { fontSize: 5 },
        { fontSize: 6 },
        { fontSize: 7 },
      ];
      // Three rows.
      expect(getPanelInitialHeight(false, presets)).toBe(256);
      // One row.
      expect(getPanelInitialHeight(false, [{ fontSize: 1 }])).toBe(96);
    });
  });
});
