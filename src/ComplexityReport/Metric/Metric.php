<?php

declare(strict_types=1);

namespace App\ComplexityReport\Metric;

/**
 * What a release can be read in: the catalog of numbers the report draws, names and explains.
 *
 * A measurement is sixty-two numbers phploc counted and the report used to plot two of them. The other
 * sixty were kept - they are what the raw output is printed from - but nothing said what they mean, so
 * they were a blob. This is the blob read out: every number the report offers has a name, the section
 * phploc prints it under, the whole it is a part of, and one line saying what it is worth.
 *
 * A metric is addressed by its **slug**, not by the phploc key behind it: the slug is what a chart is
 * shared as (`?metrics=complexity,loc`), so it is written the way the number is spoken about, and
 * `source()` is where it comes from. That is also why `complexity` is a slug of its own - the number the
 * whole report is about is `classCcnAvg` in a phploc measurement, which is nothing to hand somebody as
 * a link.
 *
 * Eight of the sixty-two are deliberately not here:
 *
 * - the five minimums (smallest class, shortest method, least methods per class, and the two smallest
 *   complexities). A minimum is the floor of a codebase, and in this dataset the floor is an empty class
 *   or a one-branch method in nearly every release measured - `classCcnMin` is 1 in 96% of the twenty
 *   thousand releases the report carries. Charting it draws a straight line at 1 and says only that
 *   somebody wrote a getter.
 * - `testClasses` and `testMethods`, which are zero everywhere: tests are never counted into the report.
 * - `ccnMethods`, the total method complexity, which is the same statement as the total complexity next
 *   to it.
 *
 * They are all still in the measurement and still in the raw output - not being worth a chart is not the
 * same as not being measured.
 */
enum Metric: string
{
    // --- Size ---------------------------------------------------------------------------------
    case Directories = 'directories';
    case Files = 'files';
    case LinesOfCode = 'loc';
    case CommentLines = 'comment-lines';
    case NonCommentLines = 'code-lines';
    case LogicalLines = 'logical-lines';
    case ClassLines = 'class-lines';
    case ClassLength = 'class-length';
    case LongestClass = 'longest-class';
    case MethodLength = 'method-length';
    case LongestMethod = 'longest-method';
    case MethodsPerClass = 'methods-per-class';
    case MostMethodsPerClass = 'most-methods-per-class';
    case FunctionLines = 'function-lines';
    case FunctionLength = 'function-length';
    case GlobalLines = 'global-lines';

    // --- Cyclomatic complexity ----------------------------------------------------------------
    case ComplexityPerLine = 'complexity-per-line';
    case Complexity = 'complexity';
    case MostComplexClass = 'most-complex-class';
    case MethodComplexity = 'method-complexity';
    case MostComplexMethod = 'most-complex-method';
    case TotalComplexity = 'total-complexity';

    // --- Dependencies -------------------------------------------------------------------------
    case GlobalAccesses = 'global-accesses';
    case GlobalConstantAccesses = 'global-constant-accesses';
    case GlobalVariableAccesses = 'global-variable-accesses';
    case SuperGlobalAccesses = 'super-global-accesses';
    case AttributeAccesses = 'attribute-accesses';
    case InstanceAttributeAccesses = 'instance-attribute-accesses';
    case StaticAttributeAccesses = 'static-attribute-accesses';
    case MethodCalls = 'method-calls';
    case InstanceMethodCalls = 'instance-method-calls';
    case StaticMethodCalls = 'static-method-calls';

    // --- Structure ----------------------------------------------------------------------------
    case Namespaces = 'namespaces';
    case Interfaces = 'interfaces';
    case Traits = 'traits';
    case Classes = 'classes';
    case AbstractClasses = 'abstract-classes';
    case ConcreteClasses = 'concrete-classes';
    case FinalClasses = 'final-classes';
    case NonFinalClasses = 'non-final-classes';
    case Methods = 'methods';
    case NonStaticMethods = 'non-static-methods';
    case StaticMethods = 'static-methods';
    case PublicMethods = 'public-methods';
    case ProtectedMethods = 'protected-methods';
    case PrivateMethods = 'private-methods';
    case Functions = 'functions';
    case NamedFunctions = 'named-functions';
    case AnonymousFunctions = 'anonymous-functions';
    case Constants = 'constants';
    case GlobalConstants = 'global-constants';
    case ClassConstants = 'class-constants';
    case PublicClassConstants = 'public-class-constants';
    case NonPublicClassConstants = 'non-public-class-constants';

    /**
     * The metric a chart opens on: the report is about cyclomatic complexity, so nothing else can be
     * what it draws when nobody said what to draw.
     */
    public static function default(): self
    {
        return self::Complexity;
    }

    /**
     * The key this number is stored under in a phploc measurement.
     */
    public function source(): string
    {
        return match ($this) {
            self::Directories => 'directories',
            self::Files => 'files',
            self::LinesOfCode => 'loc',
            self::CommentLines => 'cloc',
            self::NonCommentLines => 'ncloc',
            self::LogicalLines => 'lloc',
            self::ClassLines => 'llocClasses',
            self::ClassLength => 'classLlocAvg',
            self::LongestClass => 'classLlocMax',
            self::MethodLength => 'methodLlocAvg',
            self::LongestMethod => 'methodLlocMax',
            self::MethodsPerClass => 'averageMethodsPerClass',
            self::MostMethodsPerClass => 'maximumMethodsPerClass',
            self::FunctionLines => 'llocFunctions',
            self::FunctionLength => 'llocByNof',
            self::GlobalLines => 'llocGlobal',
            self::ComplexityPerLine => 'ccnByLloc',
            self::Complexity => 'classCcnAvg',
            self::MostComplexClass => 'classCcnMax',
            self::MethodComplexity => 'methodCcnAvg',
            self::MostComplexMethod => 'methodCcnMax',
            self::TotalComplexity => 'ccn',
            self::GlobalAccesses => 'globalAccesses',
            self::GlobalConstantAccesses => 'globalConstantAccesses',
            self::GlobalVariableAccesses => 'globalVariableAccesses',
            self::SuperGlobalAccesses => 'superGlobalVariableAccesses',
            self::AttributeAccesses => 'attributeAccesses',
            self::InstanceAttributeAccesses => 'instanceAttributeAccesses',
            self::StaticAttributeAccesses => 'staticAttributeAccesses',
            self::MethodCalls => 'methodCalls',
            self::InstanceMethodCalls => 'instanceMethodCalls',
            self::StaticMethodCalls => 'staticMethodCalls',
            self::Namespaces => 'namespaces',
            self::Interfaces => 'interfaces',
            self::Traits => 'traits',
            self::Classes => 'classes',
            self::AbstractClasses => 'abstractClasses',
            self::ConcreteClasses => 'concreteClasses',
            self::FinalClasses => 'finalClasses',
            self::NonFinalClasses => 'nonFinalClasses',
            self::Methods => 'methods',
            self::NonStaticMethods => 'nonStaticMethods',
            self::StaticMethods => 'staticMethods',
            self::PublicMethods => 'publicMethods',
            self::ProtectedMethods => 'protectedMethods',
            self::PrivateMethods => 'privateMethods',
            self::Functions => 'functions',
            self::NamedFunctions => 'namedFunctions',
            self::AnonymousFunctions => 'anonymousFunctions',
            self::Constants => 'constants',
            self::GlobalConstants => 'globalConstants',
            self::ClassConstants => 'classConstants',
            self::PublicClassConstants => 'publicClassConstants',
            self::NonPublicClassConstants => 'nonPublicClassConstants',
        };
    }

    /**
     * What the number is called - phploc's own wording wherever it prints one, so the chart and the raw
     * output name the same thing the same way.
     */
    public function label(): string
    {
        return match ($this) {
            self::Directories => 'Directories',
            self::Files => 'Files',
            self::LinesOfCode => 'Lines of code (LOC)',
            self::CommentLines => 'Comment lines (CLOC)',
            self::NonCommentLines => 'Non-comment lines (NCLOC)',
            self::LogicalLines => 'Logical lines (LLOC)',
            self::ClassLines => 'Logical lines in classes',
            self::ClassLength => 'Average class length',
            self::LongestClass => 'Longest class',
            self::MethodLength => 'Average method length',
            self::LongestMethod => 'Longest method',
            self::MethodsPerClass => 'Average methods per class',
            self::MostMethodsPerClass => 'Most methods in a class',
            self::FunctionLines => 'Logical lines in functions',
            self::FunctionLength => 'Average function length',
            self::GlobalLines => 'Logical lines outside classes and functions',
            self::ComplexityPerLine => 'Average complexity per logical line',
            self::Complexity => 'Average complexity per class',
            self::MostComplexClass => 'Most complex class',
            self::MethodComplexity => 'Average complexity per method',
            self::MostComplexMethod => 'Most complex method',
            self::TotalComplexity => 'Total cyclomatic complexity',
            self::GlobalAccesses => 'Global accesses',
            self::GlobalConstantAccesses => 'Global constants read',
            self::GlobalVariableAccesses => 'Global variables read',
            self::SuperGlobalAccesses => 'Super-globals read',
            self::AttributeAccesses => 'Attribute accesses',
            self::InstanceAttributeAccesses => 'Non-static attribute accesses',
            self::StaticAttributeAccesses => 'Static attribute accesses',
            self::MethodCalls => 'Method calls',
            self::InstanceMethodCalls => 'Non-static method calls',
            self::StaticMethodCalls => 'Static method calls',
            self::Namespaces => 'Namespaces',
            self::Interfaces => 'Interfaces',
            self::Traits => 'Traits',
            self::Classes => 'Classes',
            self::AbstractClasses => 'Abstract classes',
            self::ConcreteClasses => 'Concrete classes',
            self::FinalClasses => 'Final classes',
            self::NonFinalClasses => 'Non-final classes',
            self::Methods => 'Methods',
            self::NonStaticMethods => 'Non-static methods',
            self::StaticMethods => 'Static methods',
            self::PublicMethods => 'Public methods',
            self::ProtectedMethods => 'Protected methods',
            self::PrivateMethods => 'Private methods',
            self::Functions => 'Functions',
            self::NamedFunctions => 'Named functions',
            self::AnonymousFunctions => 'Anonymous functions',
            self::Constants => 'Constants',
            self::GlobalConstants => 'Global constants',
            self::ClassConstants => 'Class constants',
            self::PublicClassConstants => 'Public class constants',
            self::NonPublicClassConstants => 'Non-public class constants',
        };
    }

    /**
     * What the number says, in one line. This is the whole point of the catalog: a measurement anybody
     * can chart is worth nothing while the reader has to know what `llocByNof` was supposed to mean.
     */
    public function about(): string
    {
        return match ($this) {
            self::Directories => 'Directories the source files sit in.',
            self::Files => 'PHP files the measurement read.',
            self::LinesOfCode => 'Every line of every file, comments and blank lines included.',
            self::CommentLines => 'Lines that are comments - against the lines of code, how much of the source is prose.',
            self::NonCommentLines => 'Everything that is not a comment.',
            self::LogicalLines => 'Statements rather than lines: what the code does, independent of how it is formatted.',
            self::ClassLines => 'Statements that stand in a class.',
            self::ClassLength => 'Statements per class - a codebase of few long classes reads differently than one of many short ones.',
            self::LongestClass => 'The longest class of the release, in statements.',
            self::MethodLength => 'Statements per method.',
            self::LongestMethod => 'The longest method of the release, in statements.',
            self::MethodsPerClass => 'Methods per class.',
            self::MostMethodsPerClass => 'The widest class of the release, in methods.',
            self::FunctionLines => 'Statements that stand in a function rather than a class.',
            self::FunctionLength => 'Statements per function.',
            self::GlobalLines => 'Statements in neither a class nor a function - the script-shaped part of a library.',
            self::ComplexityPerLine => 'Branches per statement: how dense the decisions are, whatever the size.',
            self::Complexity => 'Branches per class, averaged - the number the whole report is about.',
            self::MostComplexClass => 'The most branching class of the release; one god class raises this and nothing else.',
            self::MethodComplexity => 'Branches per method - closer to what a reader of a single method meets.',
            self::MostComplexMethod => 'The most branching method of the release.',
            self::TotalComplexity => 'Every branch in the codebase added up - it grows with the code, so read it next to the size.',
            self::GlobalAccesses => 'Reads of global state: constants, variables and super-globals together.',
            self::GlobalConstantAccesses => 'Reads of a global constant.',
            self::GlobalVariableAccesses => 'Reads of a global variable.',
            self::SuperGlobalAccesses => 'Reads of $_GET, $_POST and their siblings - request state straight out of the language.',
            self::AttributeAccesses => 'Reads and writes of object or class properties.',
            self::InstanceAttributeAccesses => 'Properties reached through an object.',
            self::StaticAttributeAccesses => 'Properties reached through a class - shared state that no instance owns.',
            self::MethodCalls => 'Method calls, whichever way they are made.',
            self::InstanceMethodCalls => 'Calls on an object.',
            self::StaticMethodCalls => 'Calls on a class, which is a dependency nothing can substitute.',
            self::Namespaces => 'Namespaces the code is spread over.',
            self::Interfaces => 'Interfaces declared.',
            self::Traits => 'Traits declared.',
            self::Classes => 'Classes declared, abstract and concrete.',
            self::AbstractClasses => 'Classes that cannot be instantiated.',
            self::ConcreteClasses => 'Classes that can.',
            self::FinalClasses => 'Concrete classes closed against extension.',
            self::NonFinalClasses => 'Concrete classes left open to extension.',
            self::Methods => 'Methods declared on all of those classes.',
            self::NonStaticMethods => 'Methods that need an instance.',
            self::StaticMethods => 'Methods that do not.',
            self::PublicMethods => 'Methods anyone may call - the surface the library is used through.',
            self::ProtectedMethods => 'Methods only the class and what extends it may call.',
            self::PrivateMethods => 'Methods only the class itself may call.',
            self::Functions => 'Functions outside classes, named and anonymous.',
            self::NamedFunctions => 'Functions with a name.',
            self::AnonymousFunctions => 'Closures and arrow functions.',
            self::Constants => 'Constants declared, global and on classes.',
            self::GlobalConstants => 'Constants outside a class.',
            self::ClassConstants => 'Constants on a class.',
            self::PublicClassConstants => 'Class constants anyone may read.',
            self::NonPublicClassConstants => 'Class constants that stay inside.',
        };
    }

    /**
     * The section a metric is printed under, which is the section it is picked from.
     */
    public function group(): MetricGroup
    {
        return match ($this) {
            self::Directories, self::Files, self::LinesOfCode, self::CommentLines, self::NonCommentLines,
            self::LogicalLines, self::ClassLines, self::ClassLength, self::LongestClass, self::MethodLength,
            self::LongestMethod, self::MethodsPerClass, self::MostMethodsPerClass, self::FunctionLines,
            self::FunctionLength, self::GlobalLines => MetricGroup::Size,
            self::ComplexityPerLine, self::Complexity, self::MostComplexClass, self::MethodComplexity,
            self::MostComplexMethod, self::TotalComplexity => MetricGroup::Complexity,
            self::GlobalAccesses, self::GlobalConstantAccesses, self::GlobalVariableAccesses,
            self::SuperGlobalAccesses, self::AttributeAccesses, self::InstanceAttributeAccesses,
            self::StaticAttributeAccesses, self::MethodCalls, self::InstanceMethodCalls,
            self::StaticMethodCalls => MetricGroup::Dependencies,
            default => MetricGroup::Structure,
        };
    }

    /**
     * The whole this number is a part of, if it is one - `Static method calls` are a share of
     * `Method calls`, the way phploc prints them.
     *
     * It is what a percentage is read against, and it is what the interpreted measurement is indented
     * by: a part stands under its whole, which is the shape of phploc's own output.
     */
    public function partOf(): ?self
    {
        return match ($this) {
            self::CommentLines, self::NonCommentLines, self::LogicalLines => self::LinesOfCode,
            self::ClassLines, self::FunctionLines, self::GlobalLines => self::LogicalLines,
            self::GlobalConstantAccesses, self::GlobalVariableAccesses, self::SuperGlobalAccesses => self::GlobalAccesses,
            self::InstanceAttributeAccesses, self::StaticAttributeAccesses => self::AttributeAccesses,
            self::InstanceMethodCalls, self::StaticMethodCalls => self::MethodCalls,
            self::AbstractClasses, self::ConcreteClasses => self::Classes,
            self::FinalClasses, self::NonFinalClasses => self::ConcreteClasses,
            self::NonStaticMethods, self::StaticMethods, self::PublicMethods, self::ProtectedMethods,
            self::PrivateMethods => self::Methods,
            self::NamedFunctions, self::AnonymousFunctions => self::Functions,
            self::GlobalConstants, self::ClassConstants => self::Constants,
            self::PublicClassConstants, self::NonPublicClassConstants => self::ClassConstants,
            default => null,
        };
    }

    /**
     * How many decimals the number is written with, which is also how far it is rounded before it is
     * sent anywhere: a count is a whole thing that was counted, and no chart of this report is read to
     * the ten-thousandth of a class.
     *
     * The average complexity per statement is the one number small enough to need a third: it runs
     * between 0.05 and 0.30 across the whole report, so two decimals would draw it in steps.
     */
    public function decimals(): int
    {
        return match ($this) {
            self::ComplexityPerLine => 3,
            self::ClassLength, self::MethodLength, self::MethodsPerClass, self::FunctionLength,
            self::Complexity, self::MethodComplexity => 2,
            default => 0,
        };
    }

    /**
     * Whether falling is an improvement - see {@see MetricDirection}.
     */
    public function direction(): MetricDirection
    {
        return match ($this) {
            self::ComplexityPerLine, self::Complexity, self::MostComplexClass, self::MethodComplexity,
            self::MostComplexMethod => MetricDirection::Lower,
            default => MetricDirection::Neutral,
        };
    }

    /**
     * Whether the number is read against the risk bands of {@see \App\ComplexityReport\ComplexityLevel}.
     *
     * Only the averages are: the bands are written for an average cyclomatic complexity, and a maximum
     * is one class - the most complex class of a library is over fifty in most of the report, which
     * would paint every library red for a single outlier.
     */
    public function carriesLevel(): bool
    {
        return self::Complexity === $this || self::MethodComplexity === $this;
    }
}
