<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Audio;

use LightManager\Application\Dto\Language;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Infrastructure\GlAudioService;
use LightManager\Module\Audio\Infrastructure\SilentAudioService;
use LightManager\Tests\Support\PinsLanguage;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Obie implementacje portu — sprawdzane **wyłącznie tam, gdzie nie zaczyna się
 * dźwięk**.
 *
 * Granica jest ostra i celowa: silnik startuje leniwie, przy pierwszej prośbie
 * o granie, więc wszystko przed tą prośbą wolno sprawdzić w PHPUnit. Samego
 * grania nie sprawdza tu nic i nie ma czym — test, który je uruchomi, zagra
 * muzykę na maszynie ciągłej integracji i zostawi po sobie wątek.
 */
final class AudioServicesTest extends TestCase
{
    use PinsLanguage;
    use ResetsSingletons;

    protected function setUp(): void
    {
        $this->resetSingleton(GlAudioService::class);
        $this->resetSingleton(SilentAudioService::class);
    }

    protected function tearDown(): void
    {
        $this->resetSingleton(GlAudioService::class);
        $this->resetSingleton(SilentAudioService::class);
    }

    /**
     * Cisza mówi wprost, że nie ma czym zagrać — zamiast udawać, że zagrała.
     *
     * Test przy okazji sprawdza rzecz, o którą łatwo się potknąć w module bez
     * ekranu: że katalog napisów modułu **naprawdę wchodzi** pod swoim
     * przedrostkiem, więc powód nie wraca surowym kluczem.
     */
    public function testSilenceAnswersWithAReasonInsteadOfPretending(): void
    {
        $this->pinLanguage(Language::Polish);
        $translator = TranslatorService::getInstance();
        $translator->addSource(AudioSettings::ID, dirname(__DIR__, 3) . '/src/Module/Audio/lang');

        $silence = SilentAudioService::getInstance();

        self::assertFalse($silence->isAvailable());
        self::assertFalse($silence->isPlaying());
        self::assertStringContainsString('glfw', $silence->play('/tmp/utwor.mp3', 50, true));
    }

    /** Metody, które nic nie robią, mają nic nie robić także wtedy, gdy woła się je bez sensu. */
    public function testSilenceSurvivesEveryCallInEveryOrder(): void
    {
        $silence = SilentAudioService::getInstance();

        $silence->stop();
        $silence->useVolume(100);
        $silence->shutdown();
        $silence->stop();

        self::assertFalse($silence->isPlaying());
    }

    /**
     * Usługa **przed pierwszym graniem** nie ma ani silnika, ani utworu — i to
     * jest cała rzecz, którą da się o niej powiedzieć bez włączania dźwięku.
     */
    public function testEngineStaysAsleepUntilSomebodyAsksForMusic(): void
    {
        $service = GlAudioService::getInstance();

        self::assertFalse($service->isPlaying(), 'nic nie gra, dopóki nikt nie poprosił');

        // Sprzątanie przed startem jest poprawne: nie ma czego zatrzymywać.
        $service->shutdown();
        $service->stop();

        self::assertFalse($service->isPlaying());
    }

    /**
     * Dostępność zależy **wyłącznie** od rozszerzenia, a nie od tego, czy ktoś
     * już czegoś słuchał.
     */
    public function testAvailabilityFollowsTheExtensionAlone(): void
    {
        self::assertSame(
            class_exists('GL\Audio\Engine'),
            GlAudioService::getInstance()->isAvailable(),
        );
    }
}
