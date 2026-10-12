<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class A26LauncherPathAndHealthTest extends TestCase
{
    public function test_vbs_script_resolves_batch_path_using_script_full_name(): void
    {
        $vbsPath = base_path('launcher/windows/Start_Silent.vbs');
        $this->assertFileExists($vbsPath);

        $content = file_get_contents($vbsPath);

        // Must not rely on CurrentDirectory
        $this->assertStringNotContainsString('WshShell.CurrentDirectory', $content, 'VBS launcher must not rely on CurrentDirectory');

        // Must resolve from ScriptFullName
        $this->assertStringContainsString('WScript.ScriptFullName', $content, 'VBS launcher must resolve script path using WScript.ScriptFullName');
        $this->assertStringContainsString('GetParentFolderName', $content);
    }

    public function test_batch_script_includes_health_check_and_error_handling(): void
    {
        $batPath = base_path('launcher/windows/Start_Pharmacy_POS.bat');
        $this->assertFileExists($batPath);

        $content = file_get_contents($batPath);

        // Must check port 8080
        $this->assertStringContainsString('set PORT=8080', $content);

        // Must perform active health check on /up
        $this->assertStringContainsString('/up', $content, 'Batch launcher must verify /up health endpoint');
        $this->assertStringContainsString('Invoke-WebRequest', $content);

        // Must provide error diagnostics
        $this->assertStringContainsString('server_launcher.log', $content);
    }

    public function test_documentation_consistently_references_port_8080(): void
    {
        $guidePath = base_path('CLIENT_AUTOSTART_GUIDE.md');
        $this->assertFileExists($guidePath);

        $content = file_get_contents($guidePath);
        $this->assertStringNotContainsString('localhost:8000', $content, 'Documentation must not refer to port 8000');
        $this->assertStringContainsString('localhost:8080', $content);
    }
}
