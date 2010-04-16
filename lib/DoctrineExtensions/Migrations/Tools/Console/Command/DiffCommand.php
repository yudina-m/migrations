<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */
 
namespace DoctrineExtensions\Migrations\Tools\Console\Command;

use Symfony\Components\Console\Input\InputInterface,
    Symfony\Components\Console\Output\OutputInterface,
    Symfony\Components\Console\Input\InputArgument,
    Symfony\Components\Console\Input\InputOption,
    Doctrine\ORM\Tools\SchemaTool,
    DoctrineExtensions\Migrations\Configuration\Configuration;

/**
 * Command for generate migration classes by comparing your current database schema
 * to your mapping information.
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision$
 * @author  Jonathan Wage <jonwage@gmail.com>
 */
class DiffCommand extends GenerateCommand
{
    protected function configure()
    {
        $this
            ->setName('migrations:diff')
            ->setDescription('Generate a migration by comparing your current database to your mapping information.')
            ->addOption('configuration', null, InputOption::PARAMETER_OPTIONAL, 'The path to a migrations configuration file.')
            ->addOption('editor-cmd', null, InputOption::PARAMETER_OPTIONAL, 'Open file with this command upon creation.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates a migration by comparing your current database to your mapping information:

    <info>%command.full_name%</info>

You can optionally specify a <comment>--editor-cmd</comment> option to open the generated file in your favorite editor:

    <info>%command.full_name% --editor-cmd=mate</info>
EOT
        );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $configuration = $this->_getMigrationConfiguration($input, $output);

        $em = $this->getHelper('em')->getEntityManager();
        $platform = $em->getConnection()->getDatabasePlatform();
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        if (empty($metadata)) {
            $output->writeln('No mapping information to process.', 'ERROR');
            return;
        }

        $tool = new SchemaTool($em);

        $fromSchema = $em->getConnection()->getSchemaManager()->createSchema();
        $toSchema = $tool->getSchemaFromMetadata($metadata);
        $up = $this->_buildCodeFromSql($configuration, $fromSchema->getMigrateToSql($toSchema, $platform));
        $down = $this->_buildCodeFromSql($configuration, $fromSchema->getMigrateFromSql($toSchema, $platform));

        if ( ! $up && ! $down) {
            $printer->writeln('No changes detected in your mapping information.', 'ERROR');
            return;
        }

        $version = date('YmdHms');
        $path = $this->_generateMigration($configuration, $input, $version, $up, $down);

        $output->writeln(sprintf('Generated new migration class to "<info>%s</info>" from schema differences.', $path));
    }

    private function _buildCodeFromSql(Configuration $configuration, array $sql)
    {
        $code = array();
        foreach ($sql as $query) {
            if (strpos($query, $configuration->getMigrationTableName()) !== false) {
                continue;
            }
            $code[] = "\$this->_addSql('" . $query . "');";
        }
        return implode("\n", $code);
    }
}