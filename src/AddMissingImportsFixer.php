<?php

declare(strict_types=1);

namespace Tranxton\AddMissingImportsFixer;

use InvalidArgumentException;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\DefinedFixerInterface;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;
use Tranxton\AddMissingImportsFixer\Enums\ExcludedSymbolsEnum;
use Tranxton\AddMissingImportsFixer\Enums\TokenValueEnum;

final class AddMissingImportsFixer implements FixerInterface, DefinedFixerInterface, ConfigurableFixerInterface
{
    /**
     * @var int|null
     */
    private $namespaceEndIndex;

    /** @var int|null */
    private $lastUseEndIndex;

    /**
     * @var array{namespace_prefix_to_remove: string}
     */
    private $configuration;

    /**
     * @var FixerConfigurationResolverInterface
     */
    private $configurationResolver;

    /**
     * @var ExcludedSymbolsEnum
     */
    private $excludedSymbolsEnum;

    public function __construct()
    {
        $this->configurationResolver = new FixerConfigurationResolver([
            (new FixerOptionBuilder('namespace_prefix_to_remove',
                'Whether to import, not import or ignore global constants.'))
                ->setAllowedTypes(['string'])
                ->getOption(),
        ]);
        $this->excludedSymbolsEnum = new ExcludedSymbolsEnum();
    }

    public function getName(): string
    {
        return 'tranxton/add_missing_imports';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Automatically adds missing use statements for classes in test files.',
            [
                new CodeSample(
                    '<?php

namespace Tests\Unit\App;

class ExampleTest extends TestCase
{
    public function testExample()
    {
        $mock = $this->createMock(SomeService::class);
        $result = new \App\Entity\User();
    }
}
',
                    ['namespace_prefix_to_remove' => 'Tests\\Unit\\']
                ),
                new CodeSample(
                    '<?php

namespace Tests\Unit\App;

use App\SomeService;

class ExampleTest extends TestCase
{
    public function testExample()
    {
        $mock = $this->createMock(SomeService::class);
        $result = new \App\Entity\User();
    }
}
',
                    ['namespace_prefix_to_remove' => 'Tests\\Unit\\']
                ),
            ]
        );
    }

    public function isRisky(): bool
    {
        return true;
    }

    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @param  array|null  $configuration
     */
    // @phpstan-ignore-next-line iterable.type
    public function configure(?array $configuration = null): void
    {
        if (!is_array($configuration)) {
            $configuration = [];
        }

        /**
         * @var array{namespace_prefix_to_remove: string} $resolvedConfiguration
         */
        $resolvedConfiguration = $this->configurationResolver->resolve($configuration);
        $this->configuration = $resolvedConfiguration;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return PHP_VERSION_ID < 70200
            && $tokens->isTokenKindFound(T_NAMESPACE)
            && ($tokens->isTokenKindFound(T_NEW)
                || $tokens->isTokenKindFound(T_DOUBLE_COLON)
                || $tokens->isTokenKindFound(T_CATCH)
                || $tokens->isTokenKindFound(T_FUNCTION));
    }

    public function fix(SplFileInfo $file, Tokens $tokens): void
    {
        $this->applyFix($file, $tokens);
    }

    public function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        if (trim($this->configuration['namespace_prefix_to_remove']) === '') {
            throw new InvalidArgumentException('Configuration option "namespace_prefix_to_remove" is required.');
        }

        $usedClasses = array_unique(array_merge(
            $this->getUsedClassesFromDeclarations($tokens),
            $this->getUsedClassesFromClassNames($tokens),
            $this->getUsedClassesFromCatchBlocks($tokens),
            $this->getUsedClassesFromFunctionSignatures($tokens)
        ));

        if ($usedClasses === []) {
            return;
        }

        $namespace = $this->getNamespace($tokens);
        if ($namespace === '') {
            return;
        }

        $existingClasses = array_unique(array_merge($this->getImportedClasses($tokens),
            $this->getCurrentClassNames($tokens)));
        $missingClasses = array_diff($usedClasses, $existingClasses);
        if ($missingClasses === []) {
            return;
        }

        $this->addMissingClassImports($this->configuration['namespace_prefix_to_remove'], $tokens, $missingClasses,
            $namespace);
    }

    /**
     * @return array<int, string>
     */
    private function getUsedClassesFromDeclarations(Tokens $tokens): array
    {
        $usedClasses = [];

        for ($i = 0; $i < $tokens->count() - 1; $i++) {
            /** @var Token $token */
            $token = $tokens[$i];
            if (!$token->isGivenKind(T_NEW)) {
                continue;
            }

            /** @var Token $currentToken */
            $currentToken = $tokens[$tokens->getNextMeaningfulToken(++$i)];
            if (!$currentToken->isGivenKind(T_STRING)) {
                continue;
            }

            /** @var Token $nextToken */
            $nextToken = $tokens[$tokens->getNextMeaningfulToken(++$i)];
            if (in_array($nextToken->getContent(), [TokenValueEnum::T_OPEN_BRACE, TokenValueEnum::T_SEMICOLON], true)) {
                $usedClasses[] = $currentToken->getContent();

                continue;
            }

            if (!$token->isGivenKind(T_DOUBLE_COLON)) {
                continue;
            }

            /** @var Token $currentToken */
            $currentToken = $tokens[$tokens->getPrevMeaningfulToken($i)];
            /** @var Token $prevToken */
            $prevToken = $tokens[$tokens->getPrevMeaningfulToken($i - 1)];

            $currentTokenIsNotStatic = !$currentToken->isGivenKind(T_STATIC);
            $currentTokenIsNotExcludedType = !$this->excludedSymbolsEnum->isExcludedSymbol($currentToken->getContent());
            $prevTokenIsNotNsSeparator = !$prevToken->isGivenKind(T_NS_SEPARATOR);
            if ($currentTokenIsNotStatic && $currentTokenIsNotExcludedType && $prevTokenIsNotNsSeparator) {
                $usedClasses[] = $currentToken->getContent();
            }
        }

        return $usedClasses;
    }

    /**
     * @return array<int, string>
     */
    private function getUsedClassesFromClassNames(Tokens $tokens): array
    {
        $usedClasses = [];
        for ($i = 0; $i < $tokens->count() - 1; $i++) {
            /** @var Token $token */
            $token = $tokens[$i];
            if (!$token->isGivenKind(T_DOUBLE_COLON)) {
                continue;
            }

            /** @var Token $currentToken */
            $currentToken = $tokens[$tokens->getPrevMeaningfulToken($i)];
            /** @var Token $prevToken */
            $prevToken = $tokens[$tokens->getPrevMeaningfulToken($i - 1)];

            $currentTokenIsNotStaticOrVariable = !$currentToken->isGivenKind([T_STATIC, T_VARIABLE]);
            $currentTokenIsNotExcludedType = !$this->excludedSymbolsEnum->isExcludedSymbol($currentToken->getContent());
            $prevTokenIsNotNsSeparator = !$prevToken->isGivenKind(T_NS_SEPARATOR);
            if ($currentTokenIsNotStaticOrVariable && $currentTokenIsNotExcludedType && $prevTokenIsNotNsSeparator) {
                $usedClasses[] = $currentToken->getContent();
            }
        }

        return $usedClasses;
    }

    /**
     * @return array<int, string>
     */
    private function getUsedClassesFromCatchBlocks(Tokens $tokens): array
    {
        $usedClasses = [];
        for ($i = 0; $i < $tokens->count() - 1; $i++) {
            /** @var Token $token */
            $token = $tokens[$i];
            if (!$token->isGivenKind(T_CATCH)) {
                continue;
            }

            for ($j = $i; $j < $tokens->count() - 1;) {
                /** @var Token $currentToken */
                $currentToken = $tokens[$tokens->getNextMeaningfulToken(++$j)];
                /** @var Token $nextToken */
                $nextToken = $tokens[$tokens->getNextMeaningfulToken(++$j)];
                if ($currentToken->isGivenKind(T_STRING) && $nextToken->isGivenKind(T_VARIABLE)) {
                    $usedClasses[] = $currentToken->getContent();
                    $i = $j;

                    break;
                }
            }

        }

        return $usedClasses;
    }

    /**
     * @return array<int, string>
     */
    private function getUsedClassesFromFunctionSignatures(Tokens $tokens): array
    {
        $usedClasses = [];

        for ($i = 0; $i < $tokens->count() - 1; $i++) {
            /** @var Token $token */
            $token = $tokens[$i];
            if (!$token->isGivenKind(T_FUNCTION)) {
                continue;
            }

            /** @var Token $nextToken */
            $openBraceIndex = (int) $tokens->getNextTokenOfKind(++$i, [TokenValueEnum::T_OPEN_BRACE]);
            $closeBraceIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openBraceIndex);

            for ($j = $openBraceIndex + 1; $j < $closeBraceIndex; $j++) {
                $currentTokenIndex = $tokens->getNextMeaningfulToken($j);

                /** @var Token $nextToken */
                $nextToken = $tokens[$currentTokenIndex];
                if (!$nextToken->isGivenKind(T_VARIABLE)) {
                    continue;
                }

                /** @var Token $currentToken */
                $currentToken = $tokens[$tokens->getPrevMeaningfulToken($j)];
                $currentTokenIsString = $currentToken->isGivenKind(T_STRING);
                $currentTokenIsNotExcludedType = !$this->excludedSymbolsEnum->isExcludedSymbol($currentToken->getContent());
                if ($currentTokenIsString && $currentTokenIsNotExcludedType) {
                    $usedClasses[] = $currentToken->getContent();

                    $i = $j;
                }
            }

            $colonTokenIndex = $closeBraceIndex + 1;
            if ($colonTokenIndex <= $tokens->count() - 1) {
                /** @var Token $colonToken */
                $colonToken = $tokens[$colonTokenIndex];
                if ($colonToken->getContent() !== TokenValueEnum::T_COLON) {
                    continue;
                }

                for ($k = $colonTokenIndex; $k < $tokens->count() - 1;) {
                    /** @var Token $nextToken */
                    $nextToken = $tokens[$tokens->getNextMeaningfulToken($k++)];
                    $nextTokenIsNotSemicolonOrOpenCurlyBrace = !in_array(
                        $nextToken->getContent(),
                        [TokenValueEnum::T_OPEN_CURLY_BRACE, TokenValueEnum::T_SEMICOLON],
                        true
                    );
                    if ($nextTokenIsNotSemicolonOrOpenCurlyBrace) {
                        continue;
                    }

                    /** @var Token $currentToken */
                    $currentToken = $tokens[$tokens->getPrevMeaningfulToken($k)];
                    /** @var Token $prevToken */
                    $prevToken = $tokens[$tokens->getPrevMeaningfulToken($k - 1)];

                    $currentTokenIsString = $currentToken->isGivenKind(T_STRING);
                    $currentTokenIsNotExcludedType = !$this->excludedSymbolsEnum->isExcludedSymbol($currentToken->getContent());
                    $prevTokenIsColon = $prevToken->getContent() === TokenValueEnum::T_COLON;
                    if ($prevTokenIsColon && $currentTokenIsString && $currentTokenIsNotExcludedType) {
                        $usedClasses[] = $currentToken->getContent();
                    }

                    break;
                }
            }
        }

        return $usedClasses;
    }

    private function getNamespace(Tokens $tokens): string
    {
        $namespace = '';

        for ($i = 0; $i < $tokens->count(); $i++) {
            /** @var Token $token */
            $token = $tokens[$i];
            if (!$token->isGivenKind(T_NAMESPACE)) {
                continue;
            }

            for ($j = $i; $j < $tokens->count() - 1;) {
                $nextTokenIndex = (int) $tokens->getNextMeaningfulToken($j);
                /** @var Token $nextToken */
                $nextToken = $tokens[$nextTokenIndex];
                if ($nextToken->getContent() === TokenValueEnum::T_SEMICOLON) {
                    $this->namespaceEndIndex = $nextTokenIndex;

                    break 2;
                }

                $namespace .= $nextToken->getContent();

                $j = $nextTokenIndex;
            }
        }

        return $namespace;
    }

    /**
     * @return array<int, string>
     */
    private function getImportedClasses(Tokens $tokens): array
    {
        $importedClasses = [];
        for ($i = 0; $i < $tokens->count() - 1; $i++) {
            /** @var Token $token */
            $token = $tokens[$i];
            if (!$token->isGivenKind(T_USE)) {
                continue;
            }

            for ($j = $i; $j < $tokens->count() - 1;) {
                $nextTokenIndex = $tokens->getNextMeaningfulToken($j++);
                /** @var Token $nextToken */
                $nextToken = $tokens[$nextTokenIndex];
                if ($nextToken->getContent() === TokenValueEnum::T_SEMICOLON) {
                    /** @var Token $prevToken */
                    $prevToken = $tokens[$tokens->getPrevMeaningfulToken($j)];
                    $importedClasses [] = $prevToken->getContent();
                    $this->lastUseEndIndex = $nextTokenIndex;
                    $i = $j;

                    break;
                }
            }
        }

        return $importedClasses;
    }

    /**
     * @return array<int, string>
     */
    private function getCurrentClassNames(Tokens $tokens): array
    {
        $currentClassNames = [];
        for ($i = 0; $i < $tokens->count() - 1; $i++) {
            /** @var Token $token */
            $token = $tokens[$i];
            if (!$token->isGivenKind(T_CLASS)) {
                continue;
            }

            /** @var Token $currentToken */
            $currentToken = $tokens[$tokens->getNextMeaningfulToken($i++)];
            if (!$currentToken->isGivenKind(T_STRING)) {
                continue;
            }

            $currentClassNames[] = $currentToken->getContent();
            $curlyOpenBraceIndex = $tokens->getNextTokenOfKind($i, [TokenValueEnum::T_OPEN_CURLY_BRACE]);
            if ($curlyOpenBraceIndex !== null) {
                $curlyCloseBraceIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $curlyOpenBraceIndex);

                $i = $curlyCloseBraceIndex;
            }

        }

        return $currentClassNames;
    }

    /**
     * @param  array<int, string>  $missingClasses
     */
    private function addMissingClassImports(
        string $namespacePrefixToRemove,
        Tokens $tokens,
        array $missingClasses,
        string $testNamespace
    ): void {
        $srcNamespace = str_replace($namespacePrefixToRemove, '', $testNamespace);
        $splitNamespace = explode(TokenValueEnum::T_NS_SEPARATOR, $srcNamespace);
        $lastUseTokenIndex = ($this->lastUseEndIndex ?? (int) $this->namespaceEndIndex) + 1;

        $index = $lastUseTokenIndex;
        foreach ($missingClasses as $missingClass) {
            $tokens->insertAt($index++, new Token([T_WHITESPACE, TokenValueEnum::T_NEW_LINE]));
            $tokens->insertAt($index++, new Token([T_USE, TokenValueEnum::T_USE]));
            $tokens->insertAt($index++, new Token([T_WHITESPACE, TokenValueEnum::T_WHITE_SPACE]));

            foreach ($splitNamespace as $namespacePart) {
                $tokens->insertAt($index++, new Token([T_STRING, $namespacePart]));
                $tokens->insertAt($index++, new Token([T_NS_SEPARATOR, TokenValueEnum::T_NS_SEPARATOR]));
            }

            $tokens->insertAt($index++, new Token([T_STRING, $missingClass]));
            $tokens->insertAt($index++, new Token([T_STRING, TokenValueEnum::T_SEMICOLON]));
        }
    }

    public function supports(SplFileInfo $file)
    {
        return true;
    }
}
