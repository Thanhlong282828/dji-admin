<?php

namespace Tests\Unit\Accessibility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class AdminHeadingHierarchyTest extends TestCase
{
    #[Test]
    public function admin_views_do_not_repeat_the_shell_page_title_as_an_inner_h1(): void
    {
        $duplicates = [];
        $projectRoot = dirname(__DIR__, 3);
        $viewRoots = [$projectRoot.'/resources/views/admin', $projectRoot.'/Modules'];

        foreach ($viewRoots as $viewRoot) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewRoot));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $source = file_get_contents($file->getPathname());
                if (! is_string($source) || ! str_contains($source, 'layouts.admin') || preg_match('/@section\(["\']title["\']\s*,\s*["\']([^"\']+)["\']\)/', $source, $title) !== 1) {
                    continue;
                }

                preg_match_all('/<h1[^>]*>(.*?)<\/h1>/si', $source, $headings);
                foreach ($headings[1] as $heading) {
                    if (trim(strip_tags($heading)) === $title[1]) {
                        $duplicates[] = str_replace($projectRoot.'/', '', $file->getPathname()).': '.$title[1];
                    }
                }
            }
        }

        self::assertSame([], $duplicates, "The admin shell already renders the page H1; duplicate inner H1 headings were found:\n".implode("\n", $duplicates));
    }
}
