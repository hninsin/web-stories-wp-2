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
 * External dependencies
 */
import { useMemo, useEffect, useCallback } from '@web-stories-wp/react';
import { trackEvent } from '@web-stories-wp/tracking';

/**
 * Internal dependencies
 */
import { Layout, ScrollToTop } from '../../../components';
import { useTemplateView } from '../../../utils';

import useApi from '../../api/useApi';
import Content from './content';
import Header from './header';

function ExploreTemplates() {
  const {
    allPagesFetched,
    isLoading,
    templates,
    templatesOrderById,
    totalPages,
    totalTemplates,
    createStoryFromTemplate,
    fetchExternalTemplates,
  } = useApi(
    ({
      state: {
        templates: {
          allPagesFetched,
          isLoading,
          templates,
          templatesOrderById,
          totalPages,
          totalTemplates,
        },
      },
      actions: {
        storyApi: { createStoryFromTemplate },
        templateApi: { fetchExternalTemplates },
      },
    }) => ({
      allPagesFetched,
      isLoading,
      templates,
      templatesOrderById,
      totalPages,
      totalTemplates,
      createStoryFromTemplate,
      fetchExternalTemplates,
    })
  );

  const { filter, page, search, sort, view } = useTemplateView({
    totalPages,
  });

  useEffect(() => {
    fetchExternalTemplates();
  }, [fetchExternalTemplates]);

  const orderedTemplates = useMemo(() => {
    return templatesOrderById.map((templateId) => {
      return templates[templateId];
    });
  }, [templatesOrderById, templates]);

  const handleCreateStoryFromTemplate = useCallback(
    (templateId) => {
      const template = templates[templateId];
      trackEvent('use_template', {
        name: template.title,
        template_id: template.id,
      });
      createStoryFromTemplate(template);
    },
    [createStoryFromTemplate, templates]
  );

  const templateActions = useMemo(
    () => ({
      createStoryFromTemplate: handleCreateStoryFromTemplate,
    }),
    [handleCreateStoryFromTemplate]
  );

  return (
    <Layout.Provider>
      <Header
        isLoading={isLoading && !totalTemplates}
        filter={filter}
        sort={sort}
        templates={orderedTemplates}
        totalTemplates={totalTemplates}
        search={search}
        view={view}
      />
      <Content
        isLoading={isLoading}
        allPagesFetched={allPagesFetched}
        page={page}
        templates={orderedTemplates}
        totalTemplates={totalTemplates}
        search={search}
        view={view}
        templateActions={templateActions}
      />
      <Layout.Fixed>
        <ScrollToTop />
      </Layout.Fixed>
    </Layout.Provider>
  );
}

export default ExploreTemplates;
